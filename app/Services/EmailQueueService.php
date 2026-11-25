<?php
declare(strict_types=1);

namespace Orixo\Services;

use Orixo\Bootstrap\App;
use Orixo\Bootstrap\Database;

/**
 * Persist-first transactional email queue with idempotency keys, lease recovery, and PHPMailer SMTP delivery.
 */
final class EmailQueueService
{
    /**
     * Queue a standard operational email. Sensitive token links must use sendImmediateSensitive() instead.
     */
    public static function enqueue(
        string $recipientEmail,
        string $templateKey,
        array $payload,
        ?string $idempotencyKey = null,
        bool $attemptImmediate = true
    ): ?int {
        $pdo = Database::connection();

        if ($idempotencyKey !== null) {
            $chk = $pdo->prepare("SELECT id FROM email_queue_logs WHERE idempotency_key = ? LIMIT 1");
            $chk->execute([$idempotencyKey]);
            $existingId = $chk->fetchColumn();
            if ($existingId) {
                return (int) $existingId;
            }
        }

        $tpl = self::loadTemplate($templateKey);
        if (!$tpl || !(int) $tpl['is_active']) {
            return null;
        }

        $subject = self::renderPlaceholders((string) $tpl['subject_template'], $payload, false);

        $ins = $pdo->prepare("
            INSERT INTO email_queue_logs
            (idempotency_key, recipient_email, template_key, subject, payload_json, status, attempts, max_attempts, next_retry_at)
            VALUES (?, ?, ?, ?, ?, 'pending', 0, ?, NOW())
        ");
        $ins->execute([
            $idempotencyKey,
            $recipientEmail,
            $templateKey,
            substr($subject, 0, 255),
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            (int) App::config('mail.max_attempts', 3),
        ]);
        $jobId = (int) $pdo->lastInsertId();

        if ($attemptImmediate) {
            self::dispatchJob($jobId);
        }

        return $jobId;
    }

    /**
     * Sends token-bearing auth emails immediately without persisting plaintext tokens in payload_json.
     */
    public static function sendImmediateSensitive(
        string $recipientEmail,
        string $templateKey,
        array $sensitivePayload,
        array $redactedLogPayload,
        ?string $idempotencyKey = null
    ): bool {
        $pdo = Database::connection();
        $tpl = self::loadTemplate($templateKey);
        if (!$tpl || !(int) $tpl['is_active']) {
            return false;
        }

        $subject = self::renderPlaceholders((string) $tpl['subject_template'], $sensitivePayload, false);
        $bodyHtml = self::wrapEmailShell($subject, self::renderPlaceholders((string) $tpl['body_html_template'], $sensitivePayload, true));

        $ins = $pdo->prepare("
            INSERT INTO email_queue_logs
            (idempotency_key, recipient_email, template_key, subject, payload_json, status, attempts, max_attempts, next_retry_at)
            VALUES (?, ?, ?, ?, ?, 'processing', 1, 1, NOW())
        ");
        $ins->execute([
            $idempotencyKey,
            $recipientEmail,
            $templateKey,
            substr($subject, 0, 255),
            json_encode($redactedLogPayload, JSON_UNESCAPED_UNICODE),
        ]);
        $jobId = (int) $pdo->lastInsertId();

        try {
            self::deliverEmail($recipientEmail, $subject, $bodyHtml);
            $pdo->prepare("UPDATE email_queue_logs SET status = 'sent', sent_at = NOW() WHERE id = ?")->execute([$jobId]);
            return true;
        } catch (\Throwable $e) {
            $err = substr($e->getMessage(), 0, 1000);
            $pdo->prepare("UPDATE email_queue_logs SET status = 'permanently_failed', last_error = ? WHERE id = ?")->execute([$err, $jobId]);
            App::logError("Sensitive email job #{$jobId} failed: {$err}", 'mail');
            return false;
        }
    }

    public static function dispatchJob(int $jobId): bool
    {
        $pdo = Database::connection();
        $leaseSecs = (int) App::config('mail.processing_lease_seconds', 120);

        $claim = $pdo->prepare("
            UPDATE email_queue_logs
            SET status = 'processing', lease_expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
            WHERE id = ? AND status IN ('pending','failed') AND attempts < max_attempts
        ");
        $claim->execute([$leaseSecs, $jobId]);
        if ($claim->rowCount() === 0) {
            return false;
        }

        $stmt = $pdo->prepare("SELECT * FROM email_queue_logs WHERE id = ? LIMIT 1");
        $stmt->execute([$jobId]);
        $job = $stmt->fetch();
        if (!$job) {
            return false;
        }

        $attempts = (int) $job['attempts'] + 1;
        $maxAttempts = (int) $job['max_attempts'];
        $payload = json_decode((string) $job['payload_json'], true) ?: [];
        $tpl = self::loadTemplate((string) $job['template_key']);

        try {
            if (!$tpl) {
                throw new \RuntimeException("Email template '{$job['template_key']}' missing.");
            }
            $subject = self::renderPlaceholders((string) $tpl['subject_template'], $payload, false);
            $bodyHtml = self::wrapEmailShell($subject, self::renderPlaceholders((string) $tpl['body_html_template'], $payload, true));

            self::deliverEmail((string) $job['recipient_email'], $subject, $bodyHtml);

            $pdo->prepare("
                UPDATE email_queue_logs
                SET status = 'sent', attempts = ?, lease_expires_at = NULL, last_error = NULL, sent_at = NOW()
                WHERE id = ?
            ")->execute([$attempts, $jobId]);
            return true;
        } catch (\Throwable $e) {
            $err = substr($e->getMessage(), 0, 1000);
            $delays = App::config('mail.retry_delays_minutes', [1 => 5, 2 => 15]);
            if ($attempts >= $maxAttempts) {
                $pdo->prepare("
                    UPDATE email_queue_logs
                    SET status = 'permanently_failed', attempts = ?, lease_expires_at = NULL, last_error = ?
                    WHERE id = ?
                ")->execute([$attempts, $err, $jobId]);

                NotificationService::notifyAdmins(
                    'email_failed',
                    "Email delivery failed (#{$jobId})",
                    "Failed sending {$job['template_key']} to {$job['recipient_email']}: {$err}",
                    'email_queue',
                    $jobId,
                    '/admin/email-queue'
                );
            } else {
                $delayMin = (int) ($delays[$attempts] ?? 15);
                $pdo->prepare("
                    UPDATE email_queue_logs
                    SET status = 'failed', attempts = ?, lease_expires_at = NULL, last_error = ?,
                        next_retry_at = DATE_ADD(NOW(), INTERVAL ? MINUTE)
                    WHERE id = ?
                ")->execute([$attempts, $err, $delayMin, $jobId]);
            }
            App::logError("Email job #{$jobId} attempt {$attempts} failed: {$err}", 'mail');
            return false;
        }
    }

    public static function processDueJobs(int $limit = 25): array
    {
        $pdo = Database::connection();

        // Recover abandoned processing leases
        $pdo->exec("
            UPDATE email_queue_logs
            SET status = 'failed', lease_expires_at = NULL, last_error = COALESCE(last_error, 'Recovered expired processing lease')
            WHERE status = 'processing' AND lease_expires_at IS NOT NULL AND lease_expires_at < NOW()
        ");

        $stmt = $pdo->prepare("
            SELECT id FROM email_queue_logs
            WHERE status IN ('pending','failed')
              AND attempts < max_attempts
              AND next_retry_at <= NOW()
            ORDER BY next_retry_at ASC, id ASC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $processed = 0;
        $sent = 0;
        $failed = 0;
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $id) {
            $processed++;
            if (self::dispatchJob((int) $id)) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return ['processed' => $processed, 'sent' => $sent, 'failed' => $failed];
    }

    private static function deliverEmail(string $to, string $subject, string $htmlBody): void
    {
        $driver = strtolower((string) App::config('mail.driver', 'apps_script'));
        if ($driver === 'apps_script') {
            self::sendViaAppsScript($to, $subject, $htmlBody);
            return;
        }
        self::sendViaPhpMailer($to, $subject, $htmlBody);
    }

    private static function sendViaAppsScript(string $to, string $subject, string $htmlBody): void
    {
        $cfg = App::config('mail.apps_script', []);
        $webhookUrl = trim((string) ($cfg['webhook_url'] ?? ''));
        $secret = trim((string) ($cfg['secret'] ?? ''));

        if ($webhookUrl === '' || !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('APPS_SCRIPT_WEBHOOK_URL is not configured in .env.');
        }
        if ($secret === '') {
            throw new \RuntimeException('APPS_SCRIPT_SECRET is not configured in .env.');
        }

        $textBody = trim(strip_tags(str_replace(['<br>', '<br/>', '</p>', '</li>'], ["\n", "\n", "\n\n", "\n"], $htmlBody)));
        $payload = json_encode([
            'secret' => $secret,
            'to' => $to,
            'subject' => $subject,
            'htmlBody' => $htmlBody,
            'textBody' => $textBody,
            'fromName' => (string) App::config('mail.from_name', 'Orixo Studio'),
            'replyTo' => setting('email.reply_to', (string) App::config('mail.from_address', '')),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => (int) ($cfg['timeout'] ?? 15),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('Apps Script request failed: ' . $curlErr);
        }

        $decoded = json_decode((string) $response, true);
        if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded) || empty($decoded['success'])) {
            $errMsg = is_array($decoded) && !empty($decoded['error'])
                ? (string) $decoded['error']
                : "HTTP {$httpCode} unexpected response from Apps Script.";
            throw new \RuntimeException($errMsg);
        }
    }

    private static function sendViaPhpMailer(string $to, string $subject, string $htmlBody): void
    {
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            throw new \RuntimeException('PHPMailer is not installed. Run composer install.');
        }
        $cfg = App::config('mail');
        if (empty($cfg['host']) || $cfg['host'] === 'smtp.example.com') {
            throw new \RuntimeException('SMTP host is not configured in .env.');
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string) $cfg['host'];
        $mail->Port = (int) $cfg['port'];
        $mail->CharSet = 'UTF-8';

        if (!empty($cfg['username'])) {
            $mail->SMTPAuth = true;
            $mail->Username = (string) $cfg['username'];
            $mail->Password = (string) $cfg['password'];
        }

        $enc = strtolower((string) ($cfg['encryption'] ?? 'tls'));
        if ($enc === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($enc === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->setFrom((string) $cfg['from_address'], (string) $cfg['from_name']);
        $mail->addAddress($to);
        $replyTo = setting('email.reply_to');
        if ($replyTo !== '') {
            $mail->addReplyTo($replyTo);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '</p>', '</li>'], ["\n", "\n", "\n\n", "\n"], $htmlBody)));
        $mail->send();
    }

    private static function loadTemplate(string $key): ?array
    {
        $stmt = Database::connection()->prepare("SELECT * FROM email_templates WHERE template_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function renderPlaceholders(string $template, array $data, bool $escapeHtml): string
    {
        return (string) preg_replace_callback('/\{\{([a-zA-Z0-9_]+)\}\}/', static function (array $m) use ($data, $escapeHtml): string {
            $val = (string) ($data[$m[1]] ?? '');
            return $escapeHtml ? e($val) : $val;
        }, $template);
    }

    private static function wrapEmailShell(string $subject, string $innerHtml): string
    {
        $siteName = e(setting('site.name', 'Orixo'));
        return '<!doctype html><html><body style="margin:0;padding:24px;background:#F7F5F0;font-family:Inter,Arial,sans-serif;color:#18181B;">'
            . '<div style="max-width:600px;margin:0 auto;background:#FFFFFF;border:1px solid #E4E2DD;border-radius:10px;overflow:hidden;">'
            . '<div style="background:#18181B;color:#F7F5F0;padding:20px 28px;border-bottom:3px solid #E04F26;">'
            . '<strong style="font-size:18px;letter-spacing:0.04em;">' . $siteName . '</strong>'
            . '<span style="float:right;font-size:12px;color:#A1A1AA;">Creative Digital Studio</span></div>'
            . '<div style="padding:28px;line-height:1.65;font-size:15px;">' . $innerHtml . '</div>'
            . '<div style="padding:16px 28px;background:#FAF8F5;border-top:1px solid #E4E2DD;font-size:12px;color:#71717A;">'
            . e($subject) . ' · ' . e(setting('site.address', 'Orixo Studio')) . '</div></div></body></html>';
    }
}
