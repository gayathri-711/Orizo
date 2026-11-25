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
use Orixo\Services\InvoiceService;
use Orixo\Services\NotificationService;
use Orixo\Services\PdfService;

/**
 * Isolated Client Portal JSON API (/api/v1/client/*).
 * Enforces role = client and project/resource ownership on every query.
 */
final class ClientApiController
{
    public function dashboard(): void
    {
        $user = current_user();
        $clientId = (int) $user['client_id'];
        $pdo = Database::connection();

        $pStats = $pdo->prepare("
            SELECT
                COUNT(*) AS total_projects,
                SUM(CASE WHEN status IN ('planning','in_progress','on_hold') THEN 1 ELSE 0 END) AS active_projects,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_projects
            FROM projects
            WHERE client_id = ? AND status != 'archived'
        ");
        $pStats->execute([$clientId]);
        $stats = $pStats->fetch();

        $delivStmt = $pdo->prepare("
            SELECT COUNT(*) FROM project_deliverables d
            INNER JOIN projects p ON p.id = d.project_id
            WHERE p.client_id = ? AND d.is_published_to_client = 1 AND d.review_status = 'approved_published'
        ");
        $delivStmt->execute([$clientId]);

        $invStmt = $pdo->prepare("
            SELECT COUNT(*) AS open_count, COALESCE(SUM(remaining_balance), 0) AS total_outstanding
            FROM invoices
            WHERE client_id = ? AND status IN ('issued','partially_paid') AND remaining_balance > 0
        ");
        $invStmt->execute([$clientId]);
        $invSummary = $invStmt->fetch();

        $unreadMsg = $pdo->prepare("
            SELECT COUNT(*) FROM project_messages m
            INNER JOIN project_threads t ON t.id = m.thread_id
            INNER JOIN projects p ON p.id = t.project_id
            LEFT JOIN message_reads mr ON mr.message_id = m.id AND mr.user_id = ?
            WHERE p.client_id = ? AND m.sender_user_id != ? AND mr.message_id IS NULL
        ");
        $unreadMsg->execute([(int) $user['id'], $clientId, (int) $user['id']]);

        $projects = $pdo->prepare("
            SELECT p.*, u.full_name AS pm_name, tm.designation AS pm_designation
            FROM projects p
            LEFT JOIN team_members tm ON tm.id = p.project_manager_id
            LEFT JOIN users u ON u.id = tm.user_id
            WHERE p.client_id = ? AND p.status != 'archived'
            ORDER BY p.updated_at DESC
            LIMIT 6
        ");
        $projects->execute([$clientId]);

        $notifStmt = $pdo->prepare("
            SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 8
        ");
        $notifStmt->execute([(int) $user['id']]);

        $invList = $pdo->prepare("
            SELECT i.*, p.title AS project_title, p.project_code
            FROM invoices i
            INNER JOIN projects p ON p.id = i.project_id
            WHERE i.client_id = ? AND i.status != 'draft'
            ORDER BY i.issue_date DESC LIMIT 5
        ");
        $invList->execute([$clientId]);
        $invoices = array_map(static function (array $inv): array {
            $inv['is_overdue'] = InvoiceService::isOverdue($inv);
            $inv['formatted_total'] = format_currency($inv['total_amount'], $inv['currency_code']);
            $inv['formatted_balance'] = format_currency($inv['remaining_balance'], $inv['currency_code']);
            return $inv;
        }, $invList->fetchAll());

        json_response(true, 'Client dashboard loaded.', [
            'kpis' => [
                'total_projects' => (int) ($stats['total_projects'] ?? 0),
                'active_projects' => (int) ($stats['active_projects'] ?? 0),
                'completed_projects' => (int) ($stats['completed_projects'] ?? 0),
                'published_deliverables' => (int) $delivStmt->fetchColumn(),
                'open_invoices' => (int) ($invSummary['open_count'] ?? 0),
                'outstanding_balance_formatted' => format_currency($invSummary['total_outstanding'] ?? 0, setting('finance.default_currency', 'INR')),
                'unread_messages' => (int) $unreadMsg->fetchColumn(),
            ],
            'projects' => $projects->fetchAll(),
            'notifications' => $notifStmt->fetchAll(),
            'invoices' => $invoices,
        ]);
    }

    public function projects(): void
    {
        $user = current_user();
        $clientId = (int) $user['client_id'];
        [$page, $perPage, $offset] = pagination_params();
        $search = trim((string) ($_GET['search'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));

        $where = ['p.client_id = ?', "p.status != 'archived'"];
        $params = [$clientId];
        if ($search !== '') {
            $where[] = '(p.title LIKE ? OR p.project_code LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($status !== '') {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }

        $pdo = Database::connection();
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM projects p WHERE " . implode(' AND ', $where));
        $cnt->execute($params);
        $total = (int) $cnt->fetchColumn();

        $sql = "SELECT p.*, s.title AS service_title, u.full_name AS pm_name
                FROM projects p
                LEFT JOIN services s ON s.id = p.service_id
                LEFT JOIN team_members tm ON tm.id = p.project_manager_id
                LEFT JOIN users u ON u.id = tm.user_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY p.updated_at DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        json_response(true, 'Projects loaded.', $stmt->fetchAll(), [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }

    public function projectDetail(array $params): void
    {
        $project = ProjectAccessMiddleware::assertCanAccessProject((int) $params['id']);
        $pid = (int) $project['id'];
        $pdo = Database::connection();

        $pm = null;
        if ($project['project_manager_id']) {
            $pmStmt = $pdo->prepare("SELECT u.full_name, tm.designation FROM team_members tm INNER JOIN users u ON u.id = tm.user_id WHERE tm.id = ?");
            $pmStmt->execute([(int) $project['project_manager_id']]);
            $pm = $pmStmt->fetch();
        }

        $teamStmt = $pdo->prepare("
            SELECT u.full_name, tm.designation, pa.project_role
            FROM project_assignments pa
            INNER JOIN team_members tm ON tm.id = pa.team_member_id
            INNER JOIN users u ON u.id = tm.user_id
            WHERE pa.project_id = ? AND tm.status = 'active'
        ");
        $teamStmt->execute([$pid]);

        $msStmt = $pdo->prepare("SELECT * FROM project_milestones WHERE project_id = ? ORDER BY display_order ASC, planned_date ASC");
        $msStmt->execute([$pid]);

        $tStmt = $pdo->prepare("
            SELECT id, milestone_id, title, priority, status, due_date, updated_at
            FROM project_tasks
            WHERE project_id = ? AND is_archived = 0 AND is_client_visible = 1
            ORDER BY FIELD(status,'blocked','review','in_progress','not_started','completed'), due_date ASC
        ");
        $tStmt->execute([$pid]);

        $bdStmt = $pdo->prepare("
            SELECT
                SUM(CASE WHEN status = 'not_started' THEN 1 ELSE 0 END) AS not_started,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress,
                SUM(CASE WHEN status = 'review' THEN 1 ELSE 0 END) AS review,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) AS blocked
            FROM project_tasks WHERE project_id = ? AND is_archived = 0
        ");
        $bdStmt->execute([$pid]);

        json_response(true, 'Project details loaded.', [
            'project' => $project,
            'project_manager' => $pm,
            'team' => $teamStmt->fetchAll(),
            'milestones' => $msStmt->fetchAll(),
            'tasks' => $tStmt->fetchAll(),
            'task_breakdown' => $bdStmt->fetch(),
        ]);
    }

    public function deliverables(array $params): void
    {
        $user = current_user();
        $pdo = Database::connection();
        $projectId = isset($params['id']) ? (int) $params['id'] : (int) ($_GET['project_id'] ?? 0);

        $where = ["p.client_id = ?", "d.is_published_to_client = 1", "d.review_status = 'approved_published'"];
        $qParams = [(int) $user['client_id']];
        if ($projectId > 0) {
            ProjectAccessMiddleware::assertCanAccessProject($projectId);
            $where[] = "d.project_id = ?";
            $qParams[] = $projectId;
        }

        $stmt = $pdo->prepare("
            SELECT d.id, d.project_id, d.title, d.description, d.version_label, d.published_at,
                   d.file_metadata_id, fm.original_name, fm.file_size_bytes, fm.mime_type,
                   p.title AS project_title, p.project_code, m.title AS milestone_title
            FROM project_deliverables d
            INNER JOIN projects p ON p.id = d.project_id
            INNER JOIN file_metadata fm ON fm.id = d.file_metadata_id
            LEFT JOIN project_milestones m ON m.id = d.milestone_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY d.published_at DESC
        ");
        $stmt->execute($qParams);
        json_response(true, 'Published deliverables loaded.', $stmt->fetchAll());
    }

    public function documents(array $params): void
    {
        $user = current_user();
        $pdo = Database::connection();
        $projectId = isset($params['id']) ? (int) $params['id'] : (int) ($_GET['project_id'] ?? 0);

        $where = [
            "p.client_id = ?",
            "doc.is_archived = 0",
            "(doc.folder_area = 'client_uploads' OR (doc.folder_area = 'agency_shared' AND doc.is_client_visible = 1))",
        ];
        $qParams = [(int) $user['client_id']];
        if ($projectId > 0) {
            ProjectAccessMiddleware::assertCanAccessProject($projectId);
            $where[] = "doc.project_id = ?";
            $qParams[] = $projectId;
        }

        $stmt = $pdo->prepare("
            SELECT doc.id, doc.project_id, doc.folder_area, doc.category, doc.title, doc.description,
                   doc.review_status, doc.created_at, doc.file_metadata_id,
                   fm.original_name, fm.file_size_bytes, fm.mime_type,
                   p.title AS project_title, p.project_code, u.full_name AS uploaded_by_name
            FROM project_documents doc
            INNER JOIN projects p ON p.id = doc.project_id
            INNER JOIN file_metadata fm ON fm.id = doc.file_metadata_id
            INNER JOIN users u ON u.id = doc.uploaded_by
            WHERE " . implode(' AND ', $where) . "
            ORDER BY doc.created_at DESC
        ");
        $stmt->execute($qParams);
        json_response(true, 'Documents loaded.', $stmt->fetchAll());
    }

    public function uploadClientFile(array $params): void
    {
        $project = ProjectAccessMiddleware::assertCanAccessProject((int) $params['id']);
        $user = current_user();

        $v = new Validator();
        if (!$v->validate($_POST, [
            'title' => 'required|min:2|max:180',
            'category' => 'required|in:brief,agreement,reference,other',
            'description' => 'nullable|max:1000',
        ])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }

        if (empty($_FILES['file'])) {
            json_response(false, 'Please select a file to upload.', null, [], 422, ['file' => 'File is required.']);
        }

        try {
            $meta = FileService::storePrivateUpload($_FILES['file'], 'projects', (int) App::config('uploads.max_project_bytes'), (int) $user['id']);
            $pdo = Database::connection();
            $stmt = $pdo->prepare("
                INSERT INTO project_documents
                (project_id, file_metadata_id, folder_area, category, title, description, review_status, is_client_visible, uploaded_by)
                VALUES (?, ?, 'client_uploads', ?, ?, ?, 'pending_review', 1, ?)
            ");
            $stmt->execute([
                (int) $project['id'],
                $meta['id'],
                $_POST['category'],
                trim((string) $_POST['title']),
                trim((string) ($_POST['description'] ?? '')) ?: null,
                (int) $user['id'],
            ]);
            $docId = (int) $pdo->lastInsertId();

            AuditService::log((int) $user['id'], 'client', 'document.client_uploaded', 'project_document', $docId, "Client uploaded '{$_POST['title']}' to {$project['project_code']}");
            NotificationService::notifyAdmins('client_upload', "New Client Upload on {$project['project_code']}", "{$user['full_name']} uploaded '{$_POST['title']}'.", 'project', (int) $project['id'], '/admin/projects/' . $project['id']);
            NotificationService::notifyProjectTeam((int) $project['id'], 'client_upload', "New Client Upload on {$project['project_code']}", "{$user['full_name']} uploaded '{$_POST['title']}'.", 'project', (int) $project['id'], '/admin/workspace/projects/' . $project['id']);

            json_response(true, 'File uploaded to Client Uploads for agency review.', ['id' => $docId], [], 201);
        } catch (\InvalidArgumentException $e) {
            json_response(false, $e->getMessage(), null, [], 422);
        }
    }

    public function updateClientUpload(array $params): void
    {
        $project = ProjectAccessMiddleware::assertCanAccessProject((int) $params['id']);
        $docId = (int) $params['doc_id'];
        $user = current_user();
        $data = request_json();

        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            SELECT * FROM project_documents
            WHERE id = ? AND project_id = ? AND folder_area = 'client_uploads' AND uploaded_by = ? AND is_archived = 0
            LIMIT 1
        ");
        $stmt->execute([$docId, (int) $project['id'], (int) $user['id']]);
        $doc = $stmt->fetch();
        if (!$doc) {
            json_response(false, 'Client upload not found.', null, [], 404);
        }

        if (!empty($data['archive'])) {
            $pdo->prepare("UPDATE project_documents SET is_archived = 1, review_status = 'archived' WHERE id = ?")->execute([$docId]);
            json_response(true, 'Upload archived.');
        }

        $title = trim((string) ($data['title'] ?? $doc['title']));
        $desc = trim((string) ($data['description'] ?? $doc['description'] ?? ''));
        $pdo->prepare("UPDATE project_documents SET title = ?, description = ? WHERE id = ?")->execute([$title, $desc ?: null, $docId]);
        json_response(true, 'Upload details updated.');
    }

    public function downloadFile(array $params): void
    {
        $fileId = (int) $params['file_id'];
        $user = current_user();
        $clientId = (int) $user['client_id'];
        $pdo = Database::connection();

        // Check authorized document
        $docChk = $pdo->prepare("
            SELECT 1 FROM project_documents d
            INNER JOIN projects p ON p.id = d.project_id
            WHERE d.file_metadata_id = ? AND p.client_id = ? AND d.is_archived = 0
              AND (d.folder_area = 'client_uploads' OR (d.folder_area = 'agency_shared' AND d.is_client_visible = 1))
            LIMIT 1
        ");
        $docChk->execute([$fileId, $clientId]);
        if ($docChk->fetchColumn()) {
            FileService::streamPrivateDownload($fileId);
        }

        // Check published deliverable
        $delChk = $pdo->prepare("
            SELECT 1 FROM project_deliverables d
            INNER JOIN projects p ON p.id = d.project_id
            WHERE d.file_metadata_id = ? AND p.client_id = ? AND d.is_published_to_client = 1 AND d.review_status = 'approved_published'
            LIMIT 1
        ");
        $delChk->execute([$fileId, $clientId]);
        if ($delChk->fetchColumn()) {
            FileService::streamPrivateDownload($fileId);
        }

        // Check message attachment
        $msgChk = $pdo->prepare("
            SELECT 1 FROM message_attachments ma
            INNER JOIN project_messages m ON m.id = ma.message_id
            INNER JOIN project_threads t ON t.id = m.thread_id
            INNER JOIN projects p ON p.id = t.project_id
            WHERE ma.file_metadata_id = ? AND p.client_id = ?
            LIMIT 1
        ");
        $msgChk->execute([$fileId, $clientId]);
        if ($msgChk->fetchColumn()) {
            FileService::streamPrivateDownload($fileId);
        }

        json_response(false, 'You are not authorized to download this file.', null, [], 403);
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
            json_response(true, 'No messages yet.', ['thread' => null, 'messages' => []]);
        }

        $mStmt = $pdo->prepare("
            SELECT m.*, u.full_name AS sender_name, r.slug AS sender_role,
                   (mr.message_id IS NOT NULL) AS is_read
            FROM project_messages m
            INNER JOIN users u ON u.id = m.sender_user_id
            INNER JOIN roles r ON r.id = u.role_id
            LEFT JOIN message_reads mr ON mr.message_id = m.id AND mr.user_id = ?
            WHERE m.thread_id = ?
            ORDER BY m.created_at ASC
        ");
        $mStmt->execute([(int) $user['id'], (int) $thread['id']]);
        $messages = $mStmt->fetchAll();

        $attStmt = $pdo->prepare("
            SELECT ma.message_id, ma.file_metadata_id, fm.original_name, fm.file_size_bytes
            FROM message_attachments ma
            INNER JOIN file_metadata fm ON fm.id = ma.file_metadata_id
            WHERE ma.message_id = ?
        ");
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
            json_response(false, 'Message text or an attachment is required.', null, [], 422);
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

        NotificationService::notifyAdmins('new_message', "Message on {$project['project_code']}", "{$user['full_name']}: " . mb_substr($body, 0, 100), 'project', (int) $project['id'], '/admin/projects/' . $project['id']);
        NotificationService::notifyProjectTeam((int) $project['id'], 'new_message', "Message on {$project['project_code']}", "{$user['full_name']}: " . mb_substr($body, 0, 100), 'project', (int) $project['id'], '/admin/workspace/projects/' . $project['id']);

        json_response(true, 'Message sent.', ['id' => $msgId], [], 201);
    }

    public function notifications(): void
    {
        $user = current_user();
        $stmt = Database::connection()->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
        $stmt->execute([(int) $user['id']]);
        json_response(true, 'Notifications loaded.', $stmt->fetchAll());
    }

    public function markNotificationRead(array $params): void
    {
        $user = current_user();
        Database::connection()->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?")
            ->execute([(int) $params['id'], (int) $user['id']]);
        json_response(true, 'Notification marked as read.');
    }

    public function invoices(): void
    {
        $user = current_user();
        $stmt = Database::connection()->prepare("
            SELECT i.*, p.title AS project_title, p.project_code
            FROM invoices i
            INNER JOIN projects p ON p.id = i.project_id
            WHERE i.client_id = ? AND i.status != 'draft'
            ORDER BY i.issue_date DESC, i.id DESC
        ");
        $stmt->execute([(int) $user['client_id']]);
        $rows = array_map(static function (array $inv): array {
            $inv['is_overdue'] = InvoiceService::isOverdue($inv);
            $inv['formatted_subtotal'] = format_currency($inv['subtotal'], $inv['currency_code']);
            $inv['formatted_tax'] = format_currency($inv['tax_amount'], $inv['currency_code']);
            $inv['formatted_total'] = format_currency($inv['total_amount'], $inv['currency_code']);
            $inv['formatted_paid'] = format_currency($inv['amount_paid'], $inv['currency_code']);
            $inv['formatted_balance'] = format_currency($inv['remaining_balance'], $inv['currency_code']);
            return $inv;
        }, $stmt->fetchAll());

        json_response(true, 'Invoices loaded.', $rows);
    }

    public function invoiceDetail(array $params): void
    {
        $user = current_user();
        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            SELECT i.*, p.title AS project_title, p.project_code, c.company_name, c.billing_address, c.tax_id
            FROM invoices i
            INNER JOIN projects p ON p.id = i.project_id
            INNER JOIN clients c ON c.id = i.client_id
            WHERE i.id = ? AND i.client_id = ? AND i.status != 'draft'
            LIMIT 1
        ");
        $stmt->execute([(int) $params['id'], (int) $user['client_id']]);
        $inv = $stmt->fetch();
        if (!$inv) {
            json_response(false, 'Invoice not found.', null, [], 404);
        }

        $inv['is_overdue'] = InvoiceService::isOverdue($inv);
        $inv['formatted_subtotal'] = format_currency($inv['subtotal'], $inv['currency_code']);
        $inv['formatted_tax'] = format_currency($inv['tax_amount'], $inv['currency_code']);
        $inv['formatted_total'] = format_currency($inv['total_amount'], $inv['currency_code']);
        $inv['formatted_paid'] = format_currency($inv['amount_paid'], $inv['currency_code']);
        $inv['formatted_balance'] = format_currency($inv['remaining_balance'], $inv['currency_code']);

        $items = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY display_order ASC");
        $items->execute([(int) $inv['id']]);
        $itemRows = array_map(static function (array $it) use ($inv): array {
            $it['formatted_unit_price'] = format_currency($it['unit_price'], $inv['currency_code']);
            $it['formatted_line_total'] = format_currency($it['line_total'], $inv['currency_code']);
            return $it;
        }, $items->fetchAll());

        $pays = $pdo->prepare("SELECT * FROM payments WHERE invoice_id = ? AND status = 'recorded' ORDER BY payment_date DESC");
        $pays->execute([(int) $inv['id']]);
        $payRows = array_map(static function (array $pm) use ($inv): array {
            $pm['formatted_amount'] = format_currency($pm['amount'], $inv['currency_code']);
            return $pm;
        }, $pays->fetchAll());

        json_response(true, 'Invoice loaded.', ['invoice' => $inv, 'items' => $itemRows, 'payments' => $payRows]);
    }

    public function downloadInvoicePdf(array $params): void
    {
        $user = current_user();
        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            SELECT i.*, c.company_name, c.billing_address, c.tax_id,
                   u.full_name AS client_name, u.email AS client_email,
                   p.title AS project_title, p.project_code
            FROM invoices i
            INNER JOIN clients c ON c.id = i.client_id
            INNER JOIN users u ON u.id = c.user_id
            INNER JOIN projects p ON p.id = i.project_id
            WHERE i.id = ? AND i.client_id = ? AND i.status != 'draft'
            LIMIT 1
        ");
        $stmt->execute([(int) $params['id'], (int) $user['client_id']]);
        $inv = $stmt->fetch();
        if (!$inv) {
            json_response(false, 'Invoice not found.', null, [], 404);
        }

        if ($inv['pdf_file_metadata_id']) {
            FileService::streamPrivateDownload((int) $inv['pdf_file_metadata_id'], $inv['invoice_number'] . '.pdf');
        }

        $items = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY display_order ASC");
        $items->execute([(int) $inv['id']]);
        $pays = $pdo->prepare("SELECT * FROM payments WHERE invoice_id = ? AND status = 'recorded' ORDER BY payment_date ASC");
        $pays->execute([(int) $inv['id']]);

        $pdfId = PdfService::generateInvoicePdf($inv, $items->fetchAll(), $pays->fetchAll(), (int) $user['id']);
        $pdo->prepare("UPDATE invoices SET pdf_file_metadata_id = ? WHERE id = ?")->execute([$pdfId, (int) $inv['id']]);
        FileService::streamPrivateDownload($pdfId, $inv['invoice_number'] . '.pdf');
    }

    public function updateProfile(): void
    {
        $user = current_user();
        $data = request_json();
        $v = new Validator();
        if (!$v->validate($data, [
            'full_name' => 'required|min:2|max:120',
            'phone' => 'nullable|max:30',
            'company_name' => 'required|min:2|max:160',
            'billing_address' => 'nullable|max:1000',
            'website_url' => 'nullable|url|max:255',
        ])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }

        Database::transaction(static function (\PDO $pdo) use ($user, $data): void {
            $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?")
                ->execute([trim((string) $data['full_name']), trim((string) ($data['phone'] ?? '')) ?: null, (int) $user['id']]);
            $pdo->prepare("UPDATE clients SET company_name = ?, billing_address = ?, website_url = ? WHERE id = ?")
                ->execute([
                    trim((string) $data['company_name']),
                    trim((string) ($data['billing_address'] ?? '')) ?: null,
                    trim((string) ($data['website_url'] ?? '')) ?: null,
                    (int) $user['client_id'],
                ]);
        });

        json_response(true, 'Profile updated successfully.');
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
            json_response(false, 'New password confirmation does not match.', null, [], 422, ['confirm_password' => 'Passwords do not match.']);
        }

        $res = AuthService::changePassword((int) $user['id'], (string) $data['current_password'], (string) $data['new_password']);
        if (!$res['ok']) {
            json_response(false, $res['message'], null, [], $res['code']);
        }
        json_response(true, $res['message']);
    }
}
