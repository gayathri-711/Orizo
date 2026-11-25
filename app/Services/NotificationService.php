<?php
declare(strict_types=1);

namespace Orixo\Services;

use Orixo\Bootstrap\Database;

/**
 * Dispatches in-app notifications to clients, assigned team members, and administrators.
 */
final class NotificationService
{
    public static function notifyUser(
        int $userId,
        string $eventType,
        string $title,
        string $message,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $actionUrl = null
    ): void {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, event_type, title, message, entity_type, entity_id, action_url)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            substr($eventType, 0, 60),
            substr($title, 0, 180),
            substr($message, 0, 500),
            $entityType,
            $entityId,
            $actionUrl,
        ]);
    }

    public static function notifyAdmins(
        string $eventType,
        string $title,
        string $message,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $actionUrl = null
    ): void {
        $pdo = Database::connection();
        $adminIds = $pdo->query("
            SELECT u.id FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE r.slug = 'admin' AND u.status = 'active'
        ")->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($adminIds as $aid) {
            self::notifyUser((int) $aid, $eventType, $title, $message, $entityType, $entityId, $actionUrl);
        }
    }

    public static function notifyProjectTeam(
        int $projectId,
        string $eventType,
        string $title,
        string $message,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $actionUrl = null,
        ?int $excludeUserId = null
    ): void {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id
            FROM project_assignments pa
            INNER JOIN team_members tm ON tm.id = pa.team_member_id
            INNER JOIN users u ON u.id = tm.user_id
            WHERE pa.project_id = ? AND tm.status = 'active' AND u.status = 'active'
        ");
        $stmt->execute([$projectId]);
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $uid) {
            if ($excludeUserId !== null && (int) $uid === $excludeUserId) {
                continue;
            }
            self::notifyUser((int) $uid, $eventType, $title, $message, $entityType, $entityId, $actionUrl);
        }
    }
}
