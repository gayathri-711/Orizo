<?php
declare(strict_types=1);

namespace Orixo\Middleware;

use Orixo\Bootstrap\App;
use Orixo\Bootstrap\Database;

/**
 * Database-verified session authentication & instant session invalidation middleware.
 */
final class AuthMiddleware
{
    public function handle(array $params = []): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $sessVersion = (int) ($_SESSION['token_version'] ?? 0);

        if ($userId <= 0 || $sessVersion <= 0) {
            $this->deny();
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            SELECT u.id, u.role_id, u.email, u.full_name, u.phone, u.avatar_path,
                   u.status, u.token_version, r.slug AS role_slug, r.name AS role_name,
                   c.id AS client_id, c.company_name, c.status AS client_status,
                   tm.id AS team_member_id, tm.designation, tm.status AS team_status
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            LEFT JOIN clients c ON c.user_id = u.id
            LEFT JOIN team_members tm ON tm.user_id = u.id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || $user['status'] !== 'active' || (int) $user['token_version'] !== $sessVersion) {
            App::destroySession();
            $this->deny('Your session has expired or your account status changed. Please log in again.');
        }

        if ($user['role_slug'] === 'client' && (!$user['client_id'] || $user['client_status'] !== 'active')) {
            App::destroySession();
            $this->deny('Your client profile is currently inactive.');
        }

        if ($user['role_slug'] === 'team_member' && (!$user['team_member_id'] || $user['team_status'] !== 'active')) {
            App::destroySession();
            $this->deny('Your team workspace access is currently inactive.');
        }

        set_current_user($user);
    }

    private function deny(string $message = 'Authentication required.'): never
    {
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            json_response(false, $message, null, [], 401);
        }
        redirect('/login?reason=auth_required');
    }
}
