<?php
declare(strict_types=1);

namespace Orixo\Services;

use Orixo\Bootstrap\Database;

/**
 * Computes authoritative project progress strictly from active project_tasks inside DB transactions.
 */
final class ProgressService
{
    public static function recalculateInTransaction(\PDO $pdo, int $projectId): array
    {
        $lock = $pdo->prepare("SELECT id, progress_mode, progress_percentage FROM projects WHERE id = ? FOR UPDATE");
        $lock->execute([$projectId]);
        $project = $lock->fetch();
        if (!$project) {
            throw new \RuntimeException('Project not found during progress recalculation.');
        }

        $agg = $pdo->prepare("
            SELECT
                COUNT(*) AS total_active,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_cnt,
                SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) AS blocked_cnt
            FROM project_tasks
            WHERE project_id = ? AND is_archived = 0
        ");
        $agg->execute([$projectId]);
        $counts = $agg->fetch();

        $total = (int) ($counts['total_active'] ?? 0);
        $completed = (int) ($counts['completed_cnt'] ?? 0);
        $blocked = (int) ($counts['blocked_cnt'] ?? 0);

        $computedPct = $total > 0 ? (int) round(($completed / $total) * 100) : 0;
        $newPct = $project['progress_mode'] === 'auto' ? $computedPct : (int) $project['progress_percentage'];

        $upd = $pdo->prepare("
            UPDATE projects
            SET total_active_tasks = ?,
                completed_tasks_count = ?,
                blocked_tasks_count = ?,
                progress_percentage = ?
            WHERE id = ?
        ");
        $upd->execute([$total, $completed, $blocked, $newPct, $projectId]);

        return [
            'total_active_tasks' => $total,
            'completed_tasks_count' => $completed,
            'blocked_tasks_count' => $blocked,
            'progress_mode' => $project['progress_mode'],
            'progress_percentage' => $newPct,
        ];
    }

    public static function recalculate(int $projectId): array
    {
        return Database::transaction(static fn (\PDO $pdo) => self::recalculateInTransaction($pdo, $projectId));
    }
}
