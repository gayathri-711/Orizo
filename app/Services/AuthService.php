<?php
declare(strict_types=1);

namespace Orixo\Services;

use Orixo\Bootstrap\App;
use Orixo\Bootstrap\Database;
use Orixo\Middleware\RateLimitMiddleware;

/**
 * Handles secure authentication, token activation, password resets, and session invalidation.
 */
final class AuthService
{
    public static function hashPassword(string $plain): string
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        return password_hash($plain, $algo);
    }

    public static function attemptLogin(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $ip = client_ip();

        if ($lockMsg = RateLimitMiddleware::checkLoginAllowed($email, $ip)) {
            return ['ok' => false, 'code' => 429, 'message' => $lockMsg];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            SELECT u.*, r.slug AS role_slug, c.status AS client_status, tm.status AS team_status
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            LEFT JOIN clients c ON c.user_id = u.id
            LEFT JOIN team_members tm ON tm.user_id = u.id
            WHERE u.email = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        $logAttempt = $pdo->prepare("
            INSERT INTO login_attempts (user_id, email, ip_address, user_agent, succeeded)
            VALUES (?, ?, ?, ?, ?)
        ");

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            $logAttempt->execute([$user['id'] ?? null, $email, $ip, substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), 0]);
            if ($user) {
                $fails = (int) $user['failed_login_attempts'] + 1;
                $lockUntil = $fails >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
                $pdo->prepare("UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?")
                    ->execute([$fails, $lockUntil, (int) $user['id']]);
            }
            AuditService::log($user ? (int) $user['id'] : null, $user['role_slug'] ?? null, 'auth.login_failed', 'user', $user ? (int) $user['id'] : null, "Failed login attempt for {$email}");
            return ['ok' => false, 'code' => 401, 'message' => 'Invalid email or password.'];
        }

        if ($user['status'] === 'pending_activation') {
            return ['ok' => false, 'code' => 403, 'message' => 'Your account is pending activation. Please check your invitation email.'];
        }
        if ($user['status'] !== 'active') {
            return ['ok' => false, 'code' => 403, 'message' => 'Your account is currently inactive or locked. Contact Orixo support.'];
        }
        if ($user['role_slug'] === 'client' && $user['client_status'] !== 'active') {
            return ['ok' => false, 'code' => 403, 'message' => 'Your client profile is inactive.'];
        }
        if ($user['role_slug'] === 'team_member' && $user['team_status'] !== 'active') {
            return ['ok' => false, 'code' => 403, 'message' => 'Your team member account is inactive.'];
        }

        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        if (password_needs_rehash((string) $user['password_hash'], $algo)) {
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                ->execute([self::hashPassword($password), (int) $user['id']]);
        }

        $pdo->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?")
            ->execute([(int) $user['id']]);
        $logAttempt->execute([(int) $user['id'], $email, $ip, substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), 1]);

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['token_version'] = (int) $user['token_version'];
        $_SESSION['_created_at'] = time();
        $_SESSION['_last_activity'] = time();
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

        AuditService::log((int) $user['id'], $user['role_slug'], 'auth.login_success', 'user', (int) $user['id'], 'User logged in');

        $redirect = match ($user['role_slug']) {
            'admin' => '/admin',
            'team_member' => '/admin/workspace',
            default => '/portal',
        };

        return ['ok' => true, 'redirect' => $redirect, 'role' => $user['role_slug']];
    }

    public static function createToken(int $userId, string $tokenType, int $ttlHours): string
    {
        $rawToken = bin2hex(random_bytes(32));
        $hash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + ($ttlHours * 3600));

        $pdo = Database::connection();
        // Invalidate previous unconsumed tokens of same type
        $pdo->prepare("UPDATE user_tokens SET used_at = NOW() WHERE user_id = ? AND token_type = ? AND used_at IS NULL")
            ->execute([$userId, $tokenType]);

        $pdo->prepare("INSERT INTO user_tokens (user_id, token_type, token_hash, expires_at) VALUES (?, ?, ?, ?)")
            ->execute([$userId, $tokenType, $hash, $expiresAt]);

        return $rawToken;
    }

    public static function activateAccount(string $rawToken, string $newPassword): array
    {
        $hash = hash('sha256', trim($rawToken));

        return Database::transaction(static function (\PDO $pdo) use ($hash, $newPassword): array {
            $stmt = $pdo->prepare("
                SELECT ut.*, u.email, u.full_name, u.status AS user_status
                FROM user_tokens ut
                INNER JOIN users u ON u.id = ut.user_id
                WHERE ut.token_hash = ? AND ut.token_type = 'account_activation'
                LIMIT 1 FOR UPDATE
            ");
            $stmt->execute([$hash]);
            $row = $stmt->fetch();

            if (!$row || $row['used_at'] !== null || strtotime((string) $row['expires_at']) < time()) {
                return ['ok' => false, 'code' => 400, 'message' => 'This activation link is invalid or has expired.'];
            }

            $pdo->prepare("
                UPDATE users
                SET password_hash = ?, status = 'active', email_verified_at = NOW(), token_version = token_version + 1
                WHERE id = ?
            ")->execute([self::hashPassword($newPassword), (int) $row['user_id']]);

            $pdo->prepare("UPDATE user_tokens SET used_at = NOW() WHERE id = ?")->execute([(int) $row['id']]);
            AuditService::log((int) $row['user_id'], 'client', 'auth.account_activated', 'user', (int) $row['user_id'], 'Client activated account');

            return ['ok' => true, 'message' => 'Account activated successfully. You may now sign in.'];
        });
    }

    public static function requestPasswordReset(string $email): void
    {
        $email = strtolower(trim($email));
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT id, full_name, email, status FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || $user['status'] !== 'active') {
            return; // Uniform response prevents email enumeration
        }

        $rawToken = bin2hex(random_bytes(32));
        $hash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + 1800); // 30 minutes

        $pdo->prepare("UPDATE user_tokens SET used_at = NOW() WHERE user_id = ? AND token_type = 'password_reset' AND used_at IS NULL")
            ->execute([(int) $user['id']]);
        $pdo->prepare("INSERT INTO user_tokens (user_id, token_type, token_hash, expires_at) VALUES (?, 'password_reset', ?, ?)")
            ->execute([(int) $user['id'], $hash, $expiresAt]);

        $resetUrl = App::config('app.url') . '/reset-password?token=' . urlencode($rawToken);
        EmailQueueService::sendImmediateSensitive(
            (string) $user['email'],
            'password_reset',
            ['full_name' => $user['full_name'], 'reset_url' => $resetUrl],
            ['full_name' => $user['full_name'], 'token_redacted' => true]
        );
        AuditService::log((int) $user['id'], null, 'auth.password_reset_requested', 'user', (int) $user['id'], 'Password reset requested');
    }

    public static function resetPassword(string $rawToken, string $newPassword): array
    {
        $hash = hash('sha256', trim($rawToken));

        return Database::transaction(static function (\PDO $pdo) use ($hash, $newPassword): array {
            $stmt = $pdo->prepare("
                SELECT * FROM user_tokens
                WHERE token_hash = ? AND token_type = 'password_reset'
                LIMIT 1 FOR UPDATE
            ");
            $stmt->execute([$hash]);
            $row = $stmt->fetch();

            if (!$row || $row['used_at'] !== null || strtotime((string) $row['expires_at']) < time()) {
                return ['ok' => false, 'code' => 400, 'message' => 'This password reset link is invalid or expired.'];
            }

            $pdo->prepare("
                UPDATE users
                SET password_hash = ?, failed_login_attempts = 0, locked_until = NULL, token_version = token_version + 1
                WHERE id = ?
            ")->execute([self::hashPassword($newPassword), (int) $row['user_id']]);

            $pdo->prepare("UPDATE user_tokens SET used_at = NOW() WHERE user_id = ? AND token_type = 'password_reset' AND used_at IS NULL")
                ->execute([(int) $row['user_id']]);

            AuditService::log((int) $row['user_id'], null, 'auth.password_reset_completed', 'user', (int) $row['user_id'], 'Password reset completed');
            return ['ok' => true, 'message' => 'Your password has been updated. Please sign in with your new password.'];
        });
    }

    public static function changePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT id, password_hash, role_id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, (string) $user['password_hash'])) {
            return ['ok' => false, 'code' => 422, 'message' => 'Current password is incorrect.'];
        }

        $pdo->prepare("UPDATE users SET password_hash = ?, token_version = token_version + 1 WHERE id = ?")
            ->execute([self::hashPassword($newPassword), $userId]);

        $newVer = (int) $pdo->query("SELECT token_version FROM users WHERE id = " . $userId)->fetchColumn();
        $_SESSION['token_version'] = $newVer;
        session_regenerate_id(true);

        AuditService::log($userId, current_user()['role_slug'] ?? null, 'auth.password_changed', 'user', $userId, 'User changed password');
        return ['ok' => true, 'message' => 'Password updated successfully.'];
    }

    public static function invalidateSessions(int $userId): void
    {
        Database::connection()->prepare("UPDATE users SET token_version = token_version + 1 WHERE id = ?")->execute([$userId]);
    }
}
