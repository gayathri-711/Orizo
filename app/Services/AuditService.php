<?php
declare(strict_types=1);

namespace Orixo\Services;

use Orixo\Bootstrap\Database;

/**
 * Immutable security and operational audit trail logger.
 */
final class AuditService
{
    public static function log(
        ?int $actorUserId,
        ?string $actorRole,
        string $action,
        string $entityType,
        ?int $entityId,
        string $summary,
        array $metadata = []
    ): void {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs
                (actor_user_id, actor_role, action, entity_type, entity_id, summary, metadata_json, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $actorUserId,
                $actorRole,
                substr($action, 0, 80),
                substr($entityType, 0, 60),
                $entityId,
                substr($summary, 0, 500),
                !empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
                client_ip(),
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
        } catch (\Throwable $e) {
            \Orixo\Bootstrap\App::logError('AuditService failure: ' . $e->getMessage(), 'app');
        }
    }
}
