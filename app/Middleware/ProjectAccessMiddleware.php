<?php
declare(strict_types=1);

namespace Orixo\Middleware;

use Orixo\Bootstrap\Database;

/**
 * Verifies live project ownership (Client) or project assignment (Team Member).
 */
final class ProjectAccessMiddleware
{
    public static function assertCanAccessProject(int $projectId): array
    {
        $user = current_user();
        if (!$user) {
            json_response(false, 'Authentication required.', null, [], 401);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? LIMIT 1");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch();

        if (!$project) {
            json_response(false, 'Project not found.', null, [], 404);
        }

        if ($user['role_slug'] === 'admin') {
            return $project;
        }

        if ($user['role_slug'] === 'client') {
            if ((int) $project['client_id'] !== (int) $user['client_id']) {
                json_response(false, 'Access denied to this project.', null, [], 403);
            }
            return $project;
        }

        if ($user['role_slug'] === 'team_member') {
            $chk = $pdo->prepare("
                SELECT 1 FROM project_assignments
                WHERE project_id = ? AND team_member_id = ?
                LIMIT 1
            ");
            $chk->execute([$projectId, (int) $user['team_member_id']]);
            if (!$chk->fetchColumn()) {
                json_response(false, 'You are not assigned to this project.', null, [], 403);
            }
            return $project;
        }

        json_response(false, 'Access denied.', null, [], 403);
    }
}
