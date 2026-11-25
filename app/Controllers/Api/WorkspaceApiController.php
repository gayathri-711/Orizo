<?php
declare(strict_types=1);

namespace Orixo\Controllers\Api;

use Orixo\Bootstrap\App;
use Orixo\Bootstrap\Database;
use Orixo\Helpers\Validator;
use Orixo\Middleware\ProjectAccessMiddleware;
use Orixo\Services\AuditService;
use Orixo\Services\AuthService;
use Orixo\Services\EmailQueueService;
use Orixo\Services\FileService;
use Orixo\Services\NotificationService;
use Orixo\Services\ProgressService;

/**
 * Scoped Workspace JSON API (/api/v1/workspace/*) for Admins and assigned Team Members.
 */
final class WorkspaceApiController
{
    public function dashboard(): void
    {
        $user = current_user();
        $pdo = Database::connection();

        if ($user['role_slug'] === 'admin') {
            $projects = $pdo->query("
                SELECT p.*, c.company_name
                FROM projects p INNER JOIN clients c ON c.id = p.client_id
                WHERE p.status != 'archived' ORDER BY p.updated_at DESC LIMIT 8
            ")->fetchAll();
            $tasks = $pdo->query("
                SELECT t.*, p.project_code, p.title AS project_title
                FROM project_tasks t INNER JOIN projects p ON p.id = t.project_id
                WHERE t.is_archived = 0 AND t.status != 'completed'
                ORDER BY FIELD(t.priority,'urgent','high','medium','low'), t.due_date ASC LIMIT 12
            ")->fetchAll();
        } else {
            $tmId = (int) $user['team_member_id'];
            $pStmt = $pdo->prepare("
                SELECT p.*, c.company_name, pa.project_role
                FROM projects p
                INNER JOIN project_assignments pa ON pa.project_id = p.id
                INNER JOIN clients c ON c.id = p.client_id
                WHERE pa.team_member_id = ? AND p.status != 'archived'
                ORDER BY p.updated_at DESC
            ");
            $pStmt->execute([$tmId]);
            $projects = $pStmt->fetchAll();

            $tStmt = $pdo->prepare("
                SELECT t.*, p.project_code, p.title AS project_title
                FROM project_tasks t
                INNER JOIN projects p ON p.id = t.project_id
                INNER JOIN project_assignments pa ON pa.project_id = p.id
                WHERE pa.team_member_id = ? AND t.is_archived = 0 AND t.status != 'completed'
                ORDER BY (t.assigned_to_team_id = ?) DESC, FIELD(t.priority,'urgent','high','medium','low'), t.due_date ASC
                LIMIT 15
            ");
            $tStmt->execute([$tmId, $tmId]);
            $tasks = $tStmt->fetchAll();
        }

        $notifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
        $notifs->execute([(int) $user['id']]);

        json_response(true, 'Workspace dashboard loaded.', [
            'assigned_projects_count' => count($projects),
            'open_tasks_count' => count($tasks),
            'projects' => $projects,
            'tasks' => $tasks,
            'notifications' => $notifs->fetchAll(),
        ]);
    }

    public function projects(): void
    {
        $user = current_user();
        $pdo = Database::connection();

        if ($user['role_slug'] === 'admin') {
            $rows = $pdo->query("
                SELECT p.*, c.company_name
                FROM projects p INNER JOIN clients c ON c.id = p.client_id
                WHERE p.status != 'archived' ORDER BY p.updated_at DESC
            ")->fetchAll();
        } else {
            $stmt = $pdo->prepare("
                SELECT p.*, c.company_name, pa.project_role
                FROM projects p
                INNER JOIN project_assignments pa ON pa.project_id = p.id
                INNER JOIN clients c ON c.id = p.client_id
                WHERE pa.team_member_id = ? AND p.status != 'archived'
                ORDER BY p.updated_at DESC
            ");
            $stmt->execute([(int) $user['team_member_id']]);
            $rows = $stmt->fetchAll();
        }

        json_response(true, 'Assigned projects loaded.', $rows);
    }

    public function projectDetail(array $params): void
    {
        $project = ProjectAccessMiddleware::assertCanAccessProject((int) $params['id']);
        $pid = (int) $project['id'];
        $pdo = Database::connection();

        $cStmt = $pdo->prepare("
            SELECT c.id, c.company_name, u.full_name AS contact_name, u.email AS contact_email
            FROM clients c INNER JOIN users u ON u.id = c.user_id WHERE c.id = ?
        ");
        $cStmt->execute([(int) $project['client_id']]);

        $teamStmt = $pdo->prepare("
            SELECT tm.id AS team_member_id, u.full_name, tm.designation, pa.project_role
            FROM project_assignments pa
            INNER JOIN team_members tm ON tm.id = pa.team_member_id
            INNER JOIN users u ON u.id = tm.user_id
            WHERE pa.project_id = ?
        ");
        $teamStmt->execute([$pid]);

        $msStmt = $pdo->prepare("SELECT * FROM project_milestones WHERE project_id = ? ORDER BY display_order ASC, planned_date ASC");
        $msStmt->execute([$pid]);

        $tStmt = $pdo->prepare("
            SELECT t.*, u.full_name AS assignee_name
            FROM project_tasks t
            LEFT JOIN team_members tm ON tm.id = t.assigned_to_team_id
            LEFT JOIN users u ON u.id = tm.user_id
            WHERE t.project_id = ? AND t.is_archived = 0
            ORDER BY FIELD(t.status,'blocked','review','in_progress','not_started','completed'), t.due_date ASC
        ");
        $tStmt->execute([$pid]);

        json_response(true, 'Workspace project loaded.', [
            'project' => $project,
            'client' => $cStmt->fetch(),
            'team' => $teamStmt->fetchAll(),
            'milestones' => $msStmt->fetchAll(),
            'tasks' => $tStmt->fetchAll(),
        ]);
    }

    public function createTask(array $params): void
    {
        $project = ProjectAccessMiddleware::assertCanAccessProject((int) $params['id']);
        $user = current_user();
        $data = request_json();

        $v = new Validator();
        if (!$v->validate($data, [
            'title' => 'required|min:2|max:180',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'status' => 'nullable|in:not_started,in_progress,review,completed,blocked',
            'due_date' => 'nullable|date',
        ])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }

        $res = Database::transaction(static function (\PDO $pdo) use ($project, $user, $data): array {
            $status = $data['status'] ?? 'not_started';
            $ins = $pdo->prepare("
                INSERT INTO project_tasks
                (project_id, milestone_id, assigned_to_team_id, title, description, priority, status, due_date, is_client_visible, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([
                (int) $project['id'],
                !empty($data['milestone_id']) ? (int) $data['milestone_id'] : null,
                !empty($data['assigned_to_team_id']) ? (int) $data['assigned_to_team_id'] : null,
                trim((string) $data['title']),
                trim((string) ($data['description'] ?? '')) ?: null,
                $data['priority'] ?? 'medium',
                $status,
                !empty($data['due_date']) ? $data['due_date'] : null,
                isset($data['is_client_visible']) ? ((int) $data['is_client_visible'] ? 1 : 0) : 1,
                (int) $user['id'],
            ]);
            $taskId = (int) $pdo->lastInsertId();

            $pdo->prepare("INSERT INTO task_status_history (task_id, old_status, new_status, changed_by, note) VALUES (?, NULL, ?, ?, 'Task created')")
                ->execute([$taskId, $status, (int) $user['id']]);

            $progress = ProgressService::recalculateInTransaction($pdo, (int) $project['id']);
            return ['task_id' => $taskId, 'progress' => $progress];
        });

        AuditService::log((int) $user['id'], $user['role_slug'], 'task.created', 'project_task', $res['task_id'], "Created task '{$data['title']}' on {$project['project_code']}");
        json_response(true, 'Task created and project progress updated.', $res, [], 201);
    }

    public function updateTask(array $params): void
    {
        $taskId = (int) $params['task_id'];
        $user = current_user();
        $data = request_json();
        $pdo = Database::connection();

        $tStmt = $pdo->prepare("SELECT * FROM project_tasks WHERE id = ? LIMIT 1");
        $tStmt->execute([$taskId]);
        $task = $tStmt->fetch();
        if (!$task) {
            json_response(false, 'Task not found.', null, [], 404);
        }

        $project = ProjectAccessMiddleware::assertCanAccessProject((int) $task['project_id']);

        $v = new Validator();
        if (!$v->validate($data, [
            'title' => 'nullable|min:2|max:180',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'status' => 'nullable|in:not_started,in_progress,review,completed,blocked',
            'due_date' => 'nullable|date',
        ])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }

        $newStatus = $data['status'] ?? $task['status'];
        $newArchived = isset($data['is_archived']) ? ((int) $data['is_archived'] ? 1 : 0) : (int) $task['is_archived'];

        $progress = Database::transaction(static function (\PDO $pdo) use ($task, $taskId, $user, $data, $newStatus, $newArchived, $project): array {
            $pdo->prepare("
                UPDATE project_tasks
                SET title = ?, description = ?, milestone_id = ?, assigned_to_team_id = ?, priority = ?, status = ?, due_date = ?, is_client_visible = ?, is_archived = ?
                WHERE id = ?
            ")->execute([
                trim((string) ($data['title'] ?? $task['title'])),
                array_key_exists('description', $data) ? (trim((string) $data['description']) ?: null) : $task['description'],
                array_key_exists('milestone_id', $data) ? (!empty($data['milestone_id']) ? (int) $data['milestone_id'] : null) : $task['milestone_id'],
                array_key_exists('assigned_to_team_id', $data) ? (!empty($data['assigned_to_team_id']) ? (int) $data['assigned_to_team_id'] : null) : $task['assigned_to_team_id'],
                $data['priority'] ?? $task['priority'],
                $newStatus,
                array_key_exists('due_date', $data) ? (!empty($data['due_date']) ? $data['due_date'] : null) : $task['due_date'],
                isset($data['is_client_visible']) ? ((int) $data['is_client_visible'] ? 1 : 0) : (int) $task['is_client_visible'],
                $newArchived,
                $taskId,
            ]);

            if ($newStatus !== $task['status']) {
                $pdo->prepare("INSERT INTO task_status_history (task_id, old_status, new_status, changed_by, note) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$taskId, $task['status'], $newStatus, (int) $user['id'], trim((string) ($data['status_note'] ?? '')) ?: null]);
            }

            return ProgressService::recalculateInTransaction($pdo, (int) $project['id']);
        });

        AuditService::log((int) $user['id'], $user['role_slug'], 'task.updated', 'project_task', $taskId, "Updated task #{$taskId} (status={$newStatus})");
        json_response(true, 'Task updated.', ['progress' => $progress]);
    }

    public function documents(array $params): void
    {
        $project = ProjectAccessMiddleware::assertCanAccessProject((int) $params['id']);
        $stmt = Database::connection()->prepare("
            SELECT doc.*, fm.original_name, fm.file_size_bytes, fm.mime_type, u.full_name AS uploaded_by_name
            FROM project_documents doc
            INNER JOIN file_metadata fm ON fm.id = doc.file_metadata_id
            INNER JOIN users u ON u.id = doc.uploaded_by
            WHERE doc.project_id = ? AND doc.is_archived = 0
            ORDER BY doc.created_at DESC
        ");
        $stmt->execute([(int) $project['id']]);
        json_response(true, 'Project documents loaded.', $stmt->fetchAll());
    }

    public function uploadDocument(array $params): void
    {
        $project = ProjectAccessMiddleware::assertCanAccessProject((int) $params['id']);
        $user = current_user();

        $v = new Validator();
        if (!$v->validate($_POST, [
            'title' => 'required|min:2|max:180',
            'category' => 'required|in:brief,agreement,design_doc,dev_doc,reference,other',
            'folder_area' => 'nullable|in:agency_shared,internal_only',
        ])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }
        if (empty($_FILES['file'])) {
            json_response(false, 'File is required.', null, [], 422);
        }

        // Security Rule: Team Member uploads MUST default to internal_only, not client-visible, pending review.
        if ($user['role_slug'] === 'team_member') {
            $folderArea = 'internal_only';
            $isClientVisible = 0;
            $reviewStatus = 'pending_review';
        } else {
            $folderArea = ($_POST['folder_area'] ?? 'internal_only') === 'agency_shared' ? 'agency_shared' : 'internal_only';
            $isClientVisible = ($folderArea === 'agency_shared' && !empty($_POST['is_client_visible'])) ? 1 : 0;
            $reviewStatus = 'reviewed';
        }

        try {
            $meta = FileService::storePrivateUpload($_FILES['file'], 'projects', (int) App::config('uploads.max_project_bytes'), (int) $user['id']);
            $pdo = Database::connection();
            $stmt = $pdo->prepare("
                INSERT INTO project_documents
                (project_id, file_metadata_id, folder_area, category, title, description, review_status, is_client_visible, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                (int) $project['id'],
                $meta['id'],
                $folderArea,
                $_POST['category'],
                trim((string) $_POST['title']),
                trim((string) ($_POST['description'] ?? '')) ?: null,
                $reviewStatus,
                $isClientVisible,
                (int) $user['id'],
            ]);
            $docId = (int) $pdo->lastInsertId();

            AuditService::log((int) $user['id'], $user['role_slug'], 'document.uploaded', 'project_document', $docId, "Uploaded '{$_POST['title']}' ({$folderArea}) on {$project['project_code']}");
            json_response(true, $user['role_slug'] === 'team_member' ? 'Document uploaded as internal/pending Admin review.' : 'Document uploaded.', ['id' => $docId], [], 201);
        } catch (\InvalidArgumentException $e) {
            json_response(false, $e->getMessage(), null, [], 422);
        }
    }

    public function reviewDocument(array $params): void
    {
        $docId = (int) $params['doc_id'];
        $user = current_user();
        $data = request_json();
        $pdo = Database::connection();

        $stmt = $pdo->prepare("SELECT * FROM project_documents WHERE id = ? LIMIT 1");
        $stmt->execute([$docId]);
        $doc = $stmt->fetch();
        if (!$doc) {
            json_response(false, 'Document not found.', null, [], 404);
        }
        ProjectAccessMiddleware::assertCanAccessProject((int) $doc['project_id']);

        $v = new Validator();
        if (!$v->validate($data, ['review_status' => 'required|in:reviewed,archived', 'review_notes' => 'nullable|max:1000'])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }

        // Team Members can mark reviewed/archived notes, never make internal docs client-visible
        $pdo->prepare("
            UPDATE project_documents
            SET review_status = ?, review_notes = ?, reviewed_by = ?, reviewed_at = NOW(),
                is_archived = CASE WHEN ? = 'archived' THEN 1 ELSE is_archived END
            WHERE id = ?
        ")->execute([
            $data['review_status'],
            trim((string) ($data['review_notes'] ?? '')) ?: null,
            (int) $user['id'],
            $data['review_status'],
            $docId,
        ]);

        AuditService::log((int) $user['id'], $user['role_slug'], 'document.reviewed', 'project_document', $docId, "Reviewed document #{$docId} -> {$data['review_status']}");
        json_response(true, 'Document review recorded.');
    }

    public function deliverables(array $params): void
    {
        $project = ProjectAccessMiddleware::assertCanAccessProject((int) $params['id']);
        $stmt = Database::connection()->prepare("
            SELECT d.*, fm.original_name, fm.file_size_bytes, m.title AS milestone_title,
                   su.full_name AS submitted_by_name, pu.full_name AS published_by_name
            FROM project_deliverables d
            INNER JOIN file_metadata fm ON fm.id = d.file_metadata_id
            LEFT JOIN project_milestones m ON m.id = d.milestone_id
            INNER JOIN users su ON su.id = d.submitted_by
            LEFT JOIN users pu ON pu.id = d.published_by
            WHERE d.project_id = ?
            ORDER BY d.created_at DESC
        ");
        $stmt->execute([(int) $project['id']]);
        json_response(true, 'Deliverables loaded.', $stmt->fetchAll());
    }

    public function submitDeliverable(array $params): void
    {
        $project = ProjectAccessMiddleware::assertCanAccessProject((int) $params['id']);
        $user = current_user();

        $v = new Validator();
        if (!$v->validate($_POST, [
            'title' => 'required|min:2|max:180',
            'version_label' => 'nullable|max:30',
        ])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }
        if (empty($_FILES['file'])) {
            json_response(false, 'Deliverable file is required.', null, [], 422);
        }

        try {
            $meta = FileService::storePrivateUpload($_FILES['file'], 'deliverables', (int) App::config('uploads.max_project_bytes'), (int) $user['id']);
            $delivId = Database::transaction(static function (\PDO $pdo) use ($project, $user, $meta): int {
                $stmt = $pdo->prepare("
                    INSERT INTO project_deliverables
                    (project_id, milestone_id, file_metadata_id, title, description, version_label, review_status, is_published_to_client, submitted_by)
                    VALUES (?, ?, ?, ?, ?, ?, 'pending_review', 0, ?)
                ");
                $stmt->execute([
                    (int) $project['id'],
                    !empty($_POST['milestone_id']) ? (int) $_POST['milestone_id'] : null,
                    $meta['id'],
                    trim((string) $_POST['title']),
                    trim((string) ($_POST['description'] ?? '')) ?: null,
                    trim((string) ($_POST['version_label'] ?? 'v1.0')) ?: 'v1.0',
                    (int) $user['id'],
                ]);
                $id = (int) $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO deliverable_review_history (deliverable_id, action, acted_by, notes) VALUES (?, 'submitted', ?, 'Submitted for Admin review')")
                    ->execute([$id, (int) $user['id']]);
                return $id;
            });

            AuditService::log((int) $user['id'], $user['role_slug'], 'deliverable.submitted', 'project_deliverable', $delivId, "Submitted deliverable '{$_POST['title']}' for review");
            NotificationService::notifyAdmins('deliverable_submitted', "Deliverable Awaiting Review ({$project['project_code']})", "{$user['full_name']} submitted '{$_POST['title']}'.", 'project', (int) $project['id'], '/admin/projects/' . $project['id']);

            json_response(true, 'Deliverable submitted for Admin review.', ['id' => $delivId], [], 201);
        } catch (\InvalidArgumentException $e) {
            json_response(false, $e->getMessage(), null, [], 422);
        }
    }

    public function replaceDeliverable(array $params): void
    {
        $delivId = (int) $params['id'];
        $user = current_user();
        $pdo = Database::connection();

        $stmt = $pdo->prepare("SELECT * FROM project_deliverables WHERE id = ? LIMIT 1");
        $stmt->execute([$delivId]);
        $deliv = $stmt->fetch();
        if (!$deliv) {
            json_response(false, 'Deliverable not found.', null, [], 404);
        }
        ProjectAccessMiddleware::assertCanAccessProject((int) $deliv['project_id']);

        if (!in_array($deliv['review_status'], ['pending_review', 'rejected'], true) && $user['role_slug'] !== 'admin') {
            json_response(false, 'Only pending-review or rejected deliverables can be replaced by Team Members.', null, [], 403);
        }
        if (empty($_FILES['file'])) {
            json_response(false, 'Replacement file is required.', null, [], 422);
        }

        try {
            $newMeta = FileService::storePrivateUpload($_FILES['file'], 'deliverables', (int) App::config('uploads.max_project_bytes'), (int) $user['id']);
            $oldFileId = (int) $deliv['file_metadata_id'];

            Database::transaction(static function (\PDO $pdo) use ($delivId, $newMeta, $deliv, $user): void {
                $pdo->prepare("
                    UPDATE project_deliverables
                    SET file_metadata_id = ?, version_label = ?, description = ?, review_status = 'pending_review', is_published_to_client = 0
                    WHERE id = ?
                ")->execute([
                    $newMeta['id'],
                    trim((string) ($_POST['version_label'] ?? $deliv['version_label'])),
                    trim((string) ($_POST['description'] ?? $deliv['description'] ?? '')) ?: null,
                    $delivId,
                ]);
                $pdo->prepare("INSERT INTO deliverable_review_history (deliverable_id, action, acted_by, notes) VALUES (?, 'replaced', ?, 'Uploaded revised deliverable file')")
                    ->execute([$delivId, (int) $user['id']]);
            });

            FileService::deleteIfUnreferenced($oldFileId);
            AuditService::log((int) $user['id'], $user['role_slug'], 'deliverable.replaced', 'project_deliverable', $delivId, "Replaced deliverable #{$delivId}");
            json_response(true, 'Deliverable file replaced and resubmitted for review.');
        } catch (\InvalidArgumentException $e) {
            json_response(false, $e->getMessage(), null, [], 422);
        }
    }

    public function messages(array $params): void
    {
        $project = ProjectAccessMiddleware::assertCanAccessProject((int) $params['id']);
        $user = current_user();
        $pdo = Database::connection();

        $tStmt = $pdo->prepare("SELECT * FROM project_threads WHERE project_id = ? LIMIT 1");
        $tStmt->execute([(int) $project['id']]);
        $thread = $tStmt->fetch();
        if (!$thread) {
            json_response(true, 'No messages.', ['thread' => null, 'messages' => []]);
        }

        $mStmt = $pdo->prepare("
            SELECT m.*, u.full_name AS sender_name, r.slug AS sender_role
            FROM project_messages m
            INNER JOIN users u ON u.id = m.sender_user_id
            INNER JOIN roles r ON r.id = u.role_id
            WHERE m.thread_id = ?
            ORDER BY m.created_at ASC
        ");
        $mStmt->execute([(int) $thread['id']]);
        $messages = $mStmt->fetchAll();

        $attStmt = $pdo->prepare("SELECT ma.file_metadata_id, fm.original_name, fm.file_size_bytes FROM message_attachments ma INNER JOIN file_metadata fm ON fm.id = ma.file_metadata_id WHERE ma.message_id = ?");
        $readIns = $pdo->prepare("INSERT IGNORE INTO message_reads (message_id, user_id) VALUES (?, ?)");
        foreach ($messages as &$m) {
            $attStmt->execute([(int) $m['id']]);
            $m['attachments'] = $attStmt->fetchAll();
            $readIns->execute([(int) $m['id'], (int) $user['id']]);
        }

        json_response(true, 'Messages loaded.', ['thread' => $thread, 'messages' => $messages]);
    }

    public function postMessage(array $params): void
    {
        $project = ProjectAccessMiddleware::assertCanAccessProject((int) $params['id']);
        $user = current_user();
        $body = trim((string) ($_POST['body'] ?? request_json()['body'] ?? ''));

        if ($body === '' && empty($_FILES['attachment'])) {
            json_response(false, 'Message or attachment is required.', null, [], 422);
        }

        $fileMeta = null;
        if (!empty($_FILES['attachment']) && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $fileMeta = FileService::storePrivateUpload($_FILES['attachment'], 'messages', (int) App::config('uploads.max_inquiry_bytes'), (int) $user['id']);
            } catch (\InvalidArgumentException $e) {
                json_response(false, $e->getMessage(), null, [], 422);
            }
        }

        $msgId = Database::transaction(static function (\PDO $pdo) use ($project, $user, $body, $fileMeta): int {
            $tStmt = $pdo->prepare("SELECT id FROM project_threads WHERE project_id = ? LIMIT 1");
            $tStmt->execute([(int) $project['id']]);
            $threadId = $tStmt->fetchColumn();
            if (!$threadId) {
                $pdo->prepare("INSERT INTO project_threads (project_id, subject) VALUES (?, 'General Project Discussion')")->execute([(int) $project['id']]);
                $threadId = (int) $pdo->lastInsertId();
            }
            $ins = $pdo->prepare("INSERT INTO project_messages (thread_id, sender_user_id, body) VALUES (?, ?, ?)");
            $ins->execute([(int) $threadId, (int) $user['id'], $body ?: 'Attached file']);
            $msgId = (int) $pdo->lastInsertId();
            $pdo->prepare("UPDATE project_threads SET last_message_at = NOW() WHERE id = ?")->execute([(int) $threadId]);
            $pdo->prepare("INSERT IGNORE INTO message_reads (message_id, user_id) VALUES (?, ?)")->execute([$msgId, (int) $user['id']]);
            if ($fileMeta) {
                $pdo->prepare("INSERT INTO message_attachments (message_id, file_metadata_id) VALUES (?, ?)")->execute([$msgId, $fileMeta['id']]);
            }
            return $msgId;
        });

        // Notify Client
        $cStmt = Database::connection()->prepare("SELECT u.id AS user_id, u.email, u.full_name FROM clients c INNER JOIN users u ON u.id = c.user_id WHERE c.id = ? LIMIT 1");
        $cStmt->execute([(int) $project['client_id']]);
        if ($client = $cStmt->fetch()) {
            NotificationService::notifyUser((int) $client['user_id'], 'new_message', "New Reply on {$project['project_code']}", "{$user['full_name']}: " . mb_substr($body, 0, 100), 'project', (int) $project['id'], '/portal/projects/' . $project['id']);
            EmailQueueService::enqueue((string) $client['email'], 'new_project_message', [
                'full_name' => $client['full_name'],
                'project_title' => $project['title'],
                'sender_name' => $user['full_name'],
                'message_excerpt' => mb_substr($body, 0, 160),
                'action_url' => App::config('app.url') . '/portal/projects/' . $project['id'],
            ], "msg_client:{$msgId}");
        }

        json_response(true, 'Reply sent.', ['id' => $msgId], [], 201);
    }

    public function downloadFile(array $params): void
    {
        $fileId = (int) $params['file_id'];
        $user = current_user();
        $pdo = Database::connection();

        if ($user['role_slug'] === 'admin') {
            FileService::streamPrivateDownload($fileId);
        }

        $tmId = (int) $user['team_member_id'];
        $chkSql = "
            SELECT 1 FROM project_documents d
            INNER JOIN project_assignments pa ON pa.project_id = d.project_id
            WHERE d.file_metadata_id = ? AND pa.team_member_id = ?
            UNION
            SELECT 1 FROM project_deliverables del
            INNER JOIN project_assignments pa ON pa.project_id = del.project_id
            WHERE del.file_metadata_id = ? AND pa.team_member_id = ?
            UNION
            SELECT 1 FROM message_attachments ma
            INNER JOIN project_messages m ON m.id = ma.message_id
            INNER JOIN project_threads t ON t.id = m.thread_id
            INNER JOIN project_assignments pa ON pa.project_id = t.project_id
            WHERE ma.file_metadata_id = ? AND pa.team_member_id = ?
            LIMIT 1
        ";
        $stmt = $pdo->prepare($chkSql);
        $stmt->execute([$fileId, $tmId, $fileId, $tmId, $fileId, $tmId]);
        if ($stmt->fetchColumn()) {
            FileService::streamPrivateDownload($fileId);
        }

        json_response(false, 'You are not authorized to download this file.', null, [], 403);
    }

    public function updateProfile(): void
    {
        $user = current_user();
        $data = request_json();
        $v = new Validator();
        if (!$v->validate($data, [
            'full_name' => 'required|min:2|max:120',
            'phone' => 'nullable|max:30',
            'bio' => 'nullable|max:1000',
        ])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }

        Database::transaction(static function (\PDO $pdo) use ($user, $data): void {
            $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?")
                ->execute([trim((string) $data['full_name']), trim((string) ($data['phone'] ?? '')) ?: null, (int) $user['id']]);
            if ($user['team_member_id']) {
                $pdo->prepare("UPDATE team_members SET bio = ? WHERE id = ?")
                    ->execute([trim((string) ($data['bio'] ?? '')) ?: null, (int) $user['team_member_id']]);
            }
        });
        json_response(true, 'Profile updated.');
    }

    public function changePassword(): void
    {
        $user = current_user();
        $data = request_json();
        $v = new Validator();
        if (!$v->validate($data, [
            'current_password' => 'required',
            'new_password' => 'required|strong_password',
            'confirm_password' => 'required',
        ])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }
        if (($data['new_password'] ?? '') !== ($data['confirm_password'] ?? '')) {
            json_response(false, 'Confirmation password mismatch.', null, [], 422);
        }
        $res = AuthService::changePassword((int) $user['id'], (string) $data['current_password'], (string) $data['new_password']);
        if (!$res['ok']) {
            json_response(false, $res['message'], null, [], $res['code']);
        }
        json_response(true, $res['message']);
    }
}
