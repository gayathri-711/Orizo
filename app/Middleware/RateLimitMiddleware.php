<?php
declare(strict_types=1);

namespace Orixo\Middleware;

use Orixo\Bootstrap\Database;

/**
 * Simple database & session-backed rate limiting for sensitive public/auth endpoints.
 */
final class RateLimitMiddleware
{
    public static function checkLoginAllowed(string $email, string $ip): ?string
    {
        $pdo = Database::connection();

        // Check user account explicit lock
        $uStmt = $pdo->prepare("SELECT locked_until FROM users WHERE email = ? LIMIT 1");
        $uStmt->execute([$email]);
        $lockedUntil = $uStmt->fetchColumn();
        if ($lockedUntil && strtotime((string) $lockedUntil) > time()) {
            return 'Account temporarily locked due to repeated failed attempts. Please try again in 15 minutes.';
        }

        // Check IP burst (15 failed attempts in 15 minutes)
        $ipStmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE ip_address = ? AND succeeded = 0 AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $ipStmt->execute([$ip]);
        if ((int) $ipStmt->fetchColumn() >= 15) {
            return 'Too many login attempts from this network. Please wait 15 minutes before retrying.';
        }

        return null;
    }

    public static function checkPublicAction(string $bucket, int $maxHits = 10, int $windowSeconds = 600): void
    {
        $key = '_rate_' . $bucket;
        $now = time();
        $hits = $_SESSION[$key] ?? [];
        $hits = array_values(array_filter($hits, static fn (int $t) => ($now - $t) < $windowSeconds));

        if (count($hits) >= $maxHits) {
            json_response(false, 'Too many requests. Please wait a few minutes and try again.', null, [], 429);
        }

        $hits[] = $now;
        $_SESSION[$key] = $hits;
    }
}
