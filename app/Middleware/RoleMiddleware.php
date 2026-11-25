<?php
declare(strict_types=1);

namespace Orixo\Middleware;

/**
 * Enforces database-verified user role membership.
 */
final class RoleMiddleware
{
    public function handle(array $params = [], array $allowedRoles = []): void
    {
        $user = current_user();
        if (!$user || !in_array($user['role_slug'], $allowedRoles, true)) {
            if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
                json_response(false, 'You do not have permission to perform this action.', null, [], 403);
            }
            http_response_code(403);
            render_view('public/403', ['pageTitle' => '403 — Access Denied | Orixo'], 'layouts/public_layout');
            exit;
        }
    }
}
