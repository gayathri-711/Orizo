<?php
declare(strict_types=1);

namespace Orixo\Services;

use Orixo\Bootstrap\App;
use Orixo\Bootstrap\Database;

/**
 * Handles inquiry submission, attachment registration, and strict role-safe conversion to Client & Project.
 */
final class InquiryService
{
    public static function submit(array $data, ?array $uploadedFile = null): array
    {
        $type = ($data['inquiry_type'] ?? 'project_inquiry') === 'general_contact' ? 'general_contact' : 'project_inquiry';
        $prefix = $type === 'general_contact' ? 'CNT' : 'INQ';

        $fileMeta = null;
        if ($uploadedFile && ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $fileMeta = FileService::storePrivateUpload(
                $uploadedFile,
                'inquiries',
                (int) App::config('uploads.max_inquiry_bytes'),
                null
            );
        }

        try {
            $result = Database::transaction(static function (\PDO $pdo) use ($data, $type, $prefix, $fileMeta): array {
                $refCode = $prefix . '-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
                $stmt = $pdo->prepare("
                    INSERT INTO inquiries
                    (reference_code, inquiry_type, full_name, email, phone, company_name, service_id, project_title, message_or_description, estimated_budget, expected_timeline, additional_info, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new')
                ");
                $stmt->execute([
                    $refCode,
                    $type,
                    trim((string) $data['full_name']),
                    strtolower(trim((string) $data['email'])),
                    trim((string) ($data['phone'] ?? '')) ?: null,
                    trim((string) ($data['company_name'] ?? '')) ?: null,
                    !empty($data['service_id']) ? (int) $data['service_id'] : null,
                    trim((string) ($data['project_title'] ?? '')) ?: null,
                    trim((string) $data['message_or_description']),
                    trim((string) ($data['estimated_budget'] ?? '')) ?: null,
                    trim((string) ($data['expected_timeline'] ?? '')) ?: null,
                    trim((string) ($data['additional_info'] ?? '')) ?: null,
                ]);
                $inquiryId = (int) $pdo->lastInsertId();

                if ($fileMeta) {
                    $pdo->prepare("INSERT INTO inquiry_attachments (inquiry_id, file_metadata_id) VALUES (?, ?)")
                        ->execute([$inquiryId, $fileMeta['id']]);
                }

                return ['id' => $inquiryId, 'reference_code' => $refCode];
            });
        } catch (\Throwable $e) {
            if ($fileMeta) {
                FileService::deleteIfUnreferenced((int) $fileMeta['id']);
            }
            throw $e;
        }

        $label = $type === 'general_contact' ? 'Contact Message' : 'Project Inquiry';
        EmailQueueService::enqueue(
            strtolower(trim((string) $data['email'])),
            'client_inquiry_confirmation',
            ['full_name' => trim((string) $data['full_name']), 'reference_code' => $result['reference_code']],
            "inq_confirm:{$result['id']}"
        );

        $adminEmail = (string) App::config('mail.admin_alert_email', setting('site.email'));
        if ($adminEmail !== '') {
            EmailQueueService::enqueue(
                $adminEmail,
                'admin_inquiry_notification',
                [
                    'inquiry_type_label' => $label,
                    'reference_code' => $result['reference_code'],
                    'full_name' => trim((string) $data['full_name']),
                    'email' => strtolower(trim((string) $data['email'])),
                    'company_name' => trim((string) ($data['company_name'] ?? 'Individual')),
                    'summary' => mb_substr(trim((string) $data['message_or_description']), 0, 180),
                    'action_url' => App::config('app.url') . '/admin/inquiries/' . $result['id'],
                ],
                "inq_admin:{$result['id']}"
            );
        }

        NotificationService::notifyAdmins(
            'inquiry_received',
            "New {$label}: {$result['reference_code']}",
            trim((string) $data['full_name']) . ' submitted a new request.',
            'inquiry',
            $result['id'],
            '/admin/inquiries/' . $result['id']
        );

        return $result;
    }

    /**
     * Converts an inquiry to a Client (reusing existing Client only) & Project.
     * Blocks conversion when email belongs to an Admin or Team Member.
     */
    public static function convertToClientAndProject(int $inquiryId, int $adminUserId, array $input): array
    {
        $outcome = Database::transaction(static function (\PDO $pdo) use ($inquiryId, $adminUserId, $input): array {
            $inqStmt = $pdo->prepare("SELECT * FROM inquiries WHERE id = ? LIMIT 1 FOR UPDATE");
            $inqStmt->execute([$inquiryId]);
            $inq = $inqStmt->fetch();

            if (!$inq) {
                return ['ok' => false, 'code' => 404, 'message' => 'Inquiry not found.'];
            }
            if ($inq['status'] === 'converted') {
                return ['ok' => false, 'code' => 422, 'message' => 'This inquiry has already been converted.'];
            }

            $email = strtolower(trim((string) ($input['client_email'] ?? $inq['email'])));

            $userStmt = $pdo->prepare("
                SELECT u.id, u.full_name, u.status, r.slug AS role_slug, c.id AS client_id, c.status AS client_status
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                LEFT JOIN clients c ON c.user_id = u.id
                WHERE u.email = ?
                LIMIT 1 FOR UPDATE
            ");
            $userStmt->execute([$email]);
            $existingUser = $userStmt->fetch();

            $isNewClient = false;
            $rawActivationToken = null;

            if ($existingUser) {
                if (in_array($existingUser['role_slug'], ['admin', 'team_member'], true)) {
                    AuditService::log($adminUserId, 'admin', 'inquiry.conversion_blocked_staff_conflict', 'inquiry', $inquiryId, "Blocked conversion: email {$email} belongs to {$existingUser['role_slug']}");
                    return [
                        'ok' => false,
                        'code' => 409,
                        'message' => "Conversion blocked: '{$email}' belongs to an internal {$existingUser['role_slug']} account. Request a separate client email or review manually.",
                    ];
                }

                if ($existingUser['status'] === 'inactive' || $existingUser['client_status'] === 'inactive') {
                    return [
                        'ok' => false,
                        'code' => 409,
                        'message' => "Existing client account for '{$email}' is inactive. Reactivate the client before converting a new project.",
                    ];
                }

                $clientId = (int) $existingUser['client_id'];
                $clientUserId = (int) $existingUser['id'];
                $clientName = (string) $existingUser['full_name'];
                $needsActivation = ($existingUser['status'] === 'pending_activation');
            } else {
                $isNewClient = true;
                $needsActivation = true;
                $roleId = (int) $pdo->query("SELECT id FROM roles WHERE slug = 'client' LIMIT 1")->fetchColumn();
                $clientName = trim((string) ($input['client_name'] ?? $inq['full_name']));
                $companyName = trim((string) ($input['company_name'] ?? $inq['company_name'] ?: $clientName));
                $placeholderHash = AuthService::hashPassword(bin2hex(random_bytes(24)));

                $uIns = $pdo->prepare("
                    INSERT INTO users (role_id, email, password_hash, full_name, phone, status)
                    VALUES (?, ?, ?, ?, ?, 'pending_activation')
                ");
                $uIns->execute([$roleId, $email, $placeholderHash, $clientName, $inq['phone']]);
                $clientUserId = (int) $pdo->lastInsertId();

                $cIns = $pdo->prepare("
                    INSERT INTO clients (user_id, company_name, status, created_from_inquiry_id)
                    VALUES (?, ?, 'active', ?)
                ");
                $cIns->execute([$clientUserId, $companyName, $inquiryId]);
                $clientId = (int) $pdo->lastInsertId();
            }

            $projectTitle = trim((string) ($input['project_title'] ?? $inq['project_title'] ?: 'Digital Engagement'));
            $projectDesc = trim((string) ($input['project_description'] ?? $inq['message_or_description']));
            $projectCode = 'PRJ-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $pmId = !empty($input['project_manager_id']) ? (int) $input['project_manager_id'] : null;

            $pIns = $pdo->prepare("
                INSERT INTO projects
                (project_code, client_id, service_id, project_manager_id, title, description, scope_of_work, start_date, target_completion_date, status, progress_mode, progress_percentage, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'planning', 'auto', 0, ?)
            ");
            $pIns->execute([
                $projectCode,
                $clientId,
                !empty($input['service_id']) ? (int) $input['service_id'] : ($inq['service_id'] ? (int) $inq['service_id'] : null),
                $pmId,
                $projectTitle,
                $projectDesc,
                trim((string) ($input['scope_of_work'] ?? '')) ?: null,
                !empty($input['start_date']) ? $input['start_date'] : date('Y-m-d'),
                !empty($input['target_completion_date']) ? $input['target_completion_date'] : null,
                $adminUserId,
            ]);
            $projectId = (int) $pdo->lastInsertId();

            if ($pmId) {
                $pdo->prepare("INSERT IGNORE INTO project_assignments (project_id, team_member_id, project_role, assigned_by) VALUES (?, ?, 'Project Manager', ?)")
                    ->execute([$projectId, $pmId, $adminUserId]);
            }

            $pdo->prepare("INSERT INTO project_threads (project_id, subject) VALUES (?, 'General Project Discussion')")
                ->execute([$projectId]);

            $pdo->prepare("
                UPDATE inquiries
                SET status = 'converted', converted_client_id = ?, converted_project_id = ?, reviewed_by = ?, reviewed_at = NOW()
                WHERE id = ?
            ")->execute([$clientId, $projectId, $adminUserId, $inquiryId]);

            if ($needsActivation) {
                $rawActivationToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rawActivationToken);
                $expiresAt = date('Y-m-d H:i:s', time() + (48 * 3600));
                $pdo->prepare("UPDATE user_tokens SET used_at = NOW() WHERE user_id = ? AND token_type = 'account_activation' AND used_at IS NULL")
                    ->execute([$clientUserId]);
                $pdo->prepare("INSERT INTO user_tokens (user_id, token_type, token_hash, expires_at) VALUES (?, 'account_activation', ?, ?)")
                    ->execute([$clientUserId, $tokenHash, $expiresAt]);
            }

            AuditService::log(
                $adminUserId,
                'admin',
                'inquiry.converted',
                'inquiry',
                $inquiryId,
                "Converted inquiry {$inq['reference_code']} into project {$projectCode}" . ($isNewClient ? ' (new client)' : ' (reused existing client)')
            );

            return [
                'ok' => true,
                'client_id' => $clientId,
                'client_user_id' => $clientUserId,
                'client_email' => $email,
                'client_name' => $clientName,
                'project_id' => $projectId,
                'project_code' => $projectCode,
                'project_title' => $projectTitle,
                'is_new_client' => $isNewClient,
                'raw_activation_token' => $rawActivationToken,
            ];
        });

        if (!$outcome['ok']) {
            return $outcome;
        }

        if ($outcome['raw_activation_token'] !== null) {
            $activationUrl = App::config('app.url') . '/activate-account?token=' . urlencode($outcome['raw_activation_token']);
            EmailQueueService::sendImmediateSensitive(
                $outcome['client_email'],
                'client_account_activation',
                [
                    'full_name' => $outcome['client_name'],
                    'project_title' => $outcome['project_title'],
                    'activation_url' => $activationUrl,
                ],
                ['full_name' => $outcome['client_name'], 'project_code' => $outcome['project_code'], 'token_redacted' => true]
            );
        } else {
            $actionUrl = App::config('app.url') . '/portal/projects/' . $outcome['project_id'];
            EmailQueueService::enqueue(
                $outcome['client_email'],
                'project_created',
                [
                    'full_name' => $outcome['client_name'],
                    'project_title' => $outcome['project_title'],
                    'project_code' => $outcome['project_code'],
                    'action_url' => $actionUrl,
                ],
                "project_created:{$outcome['project_id']}"
            );
        }

        NotificationService::notifyUser(
            $outcome['client_user_id'],
            'project_created',
            "Project Created: {$outcome['project_code']}",
            "Your project '{$outcome['project_title']}' is ready in your Client Portal.",
            'project',
            $outcome['project_id'],
            '/portal/projects/' . $outcome['project_id']
        );

        unset($outcome['raw_activation_token']);
        return $outcome;
    }
}
