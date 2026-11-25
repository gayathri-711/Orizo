<?php
declare(strict_types=1);

namespace Orixo\Controllers\Api;

use Orixo\Bootstrap\App;
use Orixo\Bootstrap\Database;
use Orixo\Helpers\Validator;
use Orixo\Services\AuditService;
use Orixo\Services\AuthService;
use Orixo\Services\EmailQueueService;
use Orixo\Services\FileService;
use Orixo\Services\InquiryService;
use Orixo\Services\InvoiceService;
use Orixo\Services\NotificationService;
use Orixo\Services\PdfService;
use Orixo\Services\ProgressService;

/**
 * Admin-only Operational JSON API (/api/v1/admin/*).
 */
final class AdminApiController
{
    public function dashboard(): void
    {
        $pdo = Database::connection();

        $totalClients = (int) $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
        $activeClients = (int) $pdo->query("SELECT COUNT(*) FROM clients WHERE status = 'active'")->fetchColumn();
        $projStats = $pdo->query("
            SELECT
                COUNT(*) AS total_projects,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_projects,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_projects,
                SUM(CASE WHEN blocked_tasks_count > 0 AND status NOT IN ('completed','cancelled','archived') THEN 1 ELSE 0 END) AS blocked_projects
            FROM projects WHERE status != 'archived'
        ")->fetch();
        $newInquiries = (int) $pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'new'")->fetchColumn();
        $pendingDeliv = (int) $pdo->query("SELECT COUNT(*) FROM project_deliverables WHERE review_status = 'pending_review'")->fetchColumn();

        $invByCurr = $pdo->query("
            SELECT currency_code,
                   COUNT(*) AS invoice_count,
                   COALESCE(SUM(total_amount), 0) AS total_invoiced,
                   COALESCE(SUM(amount_paid), 0) AS total_collected,
                   COALESCE(SUM(remaining_balance), 0) AS total_outstanding
            FROM invoices
            WHERE status != 'cancelled'
            GROUP BY currency_code
        ")->fetchAll();
        foreach ($invByCurr as &$row) {
            $row['formatted_invoiced'] = format_currency($row['total_invoiced'], $row['currency_code']);
            $row['formatted_collected'] = format_currency($row['total_collected'], $row['currency_code']);
            $row['formatted_outstanding'] = format_currency($row['total_outstanding'], $row['currency_code']);
        }

        $recentActivity = $pdo->query("
            SELECT a.*, u.full_name AS actor_name
            FROM activity_logs a LEFT JOIN users u ON u.id = a.actor_user_id
            ORDER BY a.created_at DESC LIMIT 12
        ")->fetchAll();

        $attentionProjects = $pdo->query("
            SELECT p.id, p.project_code, p.title, p.blocked_tasks_count, p.progress_percentage, p.status, c.company_name
            FROM projects p INNER JOIN clients c ON c.id = p.client_id
            WHERE p.blocked_tasks_count > 0 AND p.status NOT IN ('completed','cancelled','archived')
            ORDER BY p.blocked_tasks_count DESC LIMIT 6
        ")->fetchAll();

        json_response(true, 'Admin dashboard loaded.', [
            'kpis' => [
                'total_clients' => $totalClients,
                'active_clients' => $activeClients,
                'total_projects' => (int) ($projStats['total_projects'] ?? 0),
                'in_progress_projects' => (int) ($projStats['in_progress_projects'] ?? 0),
                'completed_projects' => (int) ($projStats['completed_projects'] ?? 0),
                'blocked_projects' => (int) ($projStats['blocked_projects'] ?? 0),
                'new_inquiries' => $newInquiries,
                'pending_deliverables' => $pendingDeliv,
                'total_invoices' => array_sum(array_column($invByCurr, 'invoice_count')),
            ],
            'finance_summary' => $invByCurr,
            'attention_projects' => $attentionProjects,
            'recent_activity' => $recentActivity,
        ]);
    }

    // --- CLIENTS ---
    public function clients(): void
    {
        [$page, $perPage, $offset] = pagination_params();
        $search = trim((string) ($_GET['search'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));

        $where = ['1=1'];
        $params = [];
        if ($search !== '') {
            $where[] = '(c.company_name LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($status !== '') {
            $where[] = 'u.status = ?';
            $params[] = $status;
        }

        $pdo = Database::connection();
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM clients c INNER JOIN users u ON u.id = c.user_id WHERE " . implode(' AND ', $where));
        $cnt->execute($params);
        $total = (int) $cnt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT c.*, u.email, u.full_name, u.phone, u.status AS user_status, u.last_login_at,
                   (SELECT COUNT(*) FROM projects p WHERE p.client_id = c.id) AS project_count
            FROM clients c
            INNER JOIN users u ON u.id = c.user_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.id DESC LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        json_response(true, 'Clients loaded.', $stmt->fetchAll(), ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)]);
    }

    public function createClient(): void
    {
        $user = current_user();
        $data = request_json();
        $v = new Validator();
        if (!$v->validate($data, [
            'full_name' => 'required|min:2|max:120',
            'email' => 'required|email|max:190',
            'company_name' => 'required|min:2|max:160',
            'phone' => 'nullable|max:30',
        ])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }

        $email = strtolower(trim((string) $data['email']));
        $pdo = Database::connection();
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $chk->execute([$email]);
        if ($chk->fetchColumn()) {
            json_response(false, 'An account with this email already exists.', null, [], 409);
        }

        $res = Database::transaction(static function (\PDO $pdo) use ($data, $email): array {
            $roleId = (int) $pdo->query("SELECT id FROM roles WHERE slug = 'client' LIMIT 1")->fetchColumn();
            $uIns = $pdo->prepare("INSERT INTO users (role_id, email, password_hash, full_name, phone, status) VALUES (?, ?, ?, ?, ?, 'pending_activation')");
            $uIns->execute([$roleId, $email, AuthService::hashPassword(bin2hex(random_bytes(24))), trim((string) $data['full_name']), trim((string) ($data['phone'] ?? '')) ?: null]);
            $uid = (int) $pdo->lastInsertId();

            $cIns = $pdo->prepare("INSERT INTO clients (user_id, company_name, billing_address, tax_id, website_url, notes, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
            $cIns->execute([
                $uid,
                trim((string) $data['company_name']),
                trim((string) ($data['billing_address'] ?? '')) ?: null,
                trim((string) ($data['tax_id'] ?? '')) ?: null,
                trim((string) ($data['website_url'] ?? '')) ?: null,
                trim((string) ($data['notes'] ?? '')) ?: null,
            ]);
            $cid = (int) $pdo->lastInsertId();

            $rawToken = AuthService::createToken($uid, 'account_activation', 48);
            return ['client_id' => $cid, 'user_id' => $uid, 'raw_token' => $rawToken];
        });

        $activationUrl = App::config('app.url') . '/activate-account?token=' . urlencode($res['raw_token']);
        EmailQueueService::sendImmediateSensitive(
            $email,
            'client_account_activation',
            ['full_name' => trim((string) $data['full_name']), 'project_title' => 'Your Orixo Client Portal', 'activation_url' => $activationUrl],
            ['full_name' => trim((string) $data['full_name']), 'token_redacted' => true]
        );

        AuditService::log((int) $user['id'], 'admin', 'client.created', 'client', $res['client_id'], "Created client {$data['company_name']} ({$email})");
        json_response(true, 'Client created and activation email sent.', ['id' => $res['client_id']], [], 201);
    }

    public function updateClient(array $params): void
    {
        $clientId = (int) $params['id'];
        $admin = current_user();
        $data = request_json();
        $pdo = Database::connection();

        $stmt = $pdo->prepare("SELECT c.*, u.status AS user_status FROM clients c INNER JOIN users u ON u.id = c.user_id WHERE c.id = ? LIMIT 1");
        $stmt->execute([$clientId]);
        $client = $stmt->fetch();
        if (!$client) {
            json_response(false, 'Client not found.', null, [], 404);
        }

        $newStatus = $data['status'] ?? $client['status'];
        Database::transaction(static function (\PDO $pdo) use ($clientId, $client, $data, $newStatus): void {
            $pdo->prepare("UPDATE clients SET company_name = ?, billing_address = ?, tax_id = ?, website_url = ?, notes = ?, status = ? WHERE id = ?")
                ->execute([
                    trim((string) ($data['company_name'] ?? $client['company_name'])),
                    array_key_exists('billing_address', $data) ? (trim((string) $data['billing_address']) ?: null) : $client['billing_address'],
                    array_key_exists('tax_id', $data) ? (trim((string) $data['tax_id']) ?: null) : $client['tax_id'],
                    array_key_exists('website_url', $data) ? (trim((string) $data['website_url']) ?: null) : $client['website_url'],
                    array_key_exists('notes', $data) ? (trim((string) $data['notes']) ?: null) : $client['notes'],
                    $newStatus === 'inactive' ? 'inactive' : 'active',
                    $clientId,
                ]);

            $uStatus = $newStatus === 'inactive' ? 'inactive' : ($client['user_status'] === 'pending_activation' ? 'pending_activation' : 'active');
            $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, status = ?, token_version = token_version + 1 WHERE id = ?")
                ->execute([
                    trim((string) ($data['full_name'] ?? '')) ?: $pdo->query("SELECT full_name FROM users WHERE id = " . (int) $client['user_id'])->fetchColumn(),
                    array_key_exists('phone', $data) ? (trim((string) $data['phone']) ?: null) : null,
                    $uStatus,
                    (int) $client['user_id'],
                ]);
        });

        AuditService::log((int) $admin['id'], 'admin', 'client.updated', 'client', $clientId, "Updated client #{$clientId} (status={$newStatus})");
        json_response(true, 'Client updated.');
    }

    public function resendActivation(array $params): void
    {
        $clientId = (int) $params['id'];
        $admin = current_user();
        $pdo = Database::connection();

        $stmt = $pdo->prepare("SELECT u.id, u.email, u.full_name, u.status FROM clients c INNER JOIN users u ON u.id = c.user_id WHERE c.id = ? LIMIT 1");
        $stmt->execute([$clientId]);
        $u = $stmt->fetch();
        if (!$u) {
            json_response(false, 'Client not found.', null, [], 404);
        }
        if ($u['status'] === 'active') {
            json_response(false, 'Client account is already active.', null, [], 422);
        }

        $raw = AuthService::createToken((int) $u['id'], 'account_activation', 48);
        $url = App::config('app.url') . '/activate-account?token=' . urlencode($raw);
        EmailQueueService::sendImmediateSensitive($u['email'], 'client_account_activation', ['full_name' => $u['full_name'], 'project_title' => 'Orixo Client Portal', 'activation_url' => $url], ['user_id' => $u['id'], 'token_redacted' => true]);
        AuditService::log((int) $admin['id'], 'admin', 'client.activation_resent', 'client', $clientId, "Resent activation email to {$u['email']}");
        json_response(true, 'Activation email resent.');
    }

    // --- INQUIRIES ---
    public function inquiries(): void
    {
        [$page, $perPage, $offset] = pagination_params();
        $type = trim((string) ($_GET['inquiry_type'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $search = trim((string) ($_GET['search'] ?? ''));

        $where = ['1=1'];
        $params = [];
        if ($type !== '') {
            $where[] = 'i.inquiry_type = ?';
            $params[] = $type;
        }
        if ($status !== '') {
            $where[] = 'i.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[] = '(i.reference_code LIKE ? OR i.full_name LIKE ? OR i.email LIKE ? OR i.company_name LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $pdo = Database::connection();
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM inquiries i WHERE " . implode(' AND ', $where));
        $cnt->execute($params);
        $total = (int) $cnt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT i.*, s.title AS service_title,
                   (SELECT COUNT(*) FROM inquiry_attachments ia WHERE ia.inquiry_id = i.id) AS attachment_count
            FROM inquiries i
            LEFT JOIN services s ON s.id = i.service_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY i.created_at DESC LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        json_response(true, 'Inquiries loaded.', $stmt->fetchAll(), ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)]);
    }

    public function inquiryDetail(array $params): void
    {
        $id = (int) $params['id'];
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT i.*, s.title AS service_title FROM inquiries i LEFT JOIN services s ON s.id = i.service_id WHERE i.id = ? LIMIT 1");
        $stmt->execute([$id]);
        $inq = $stmt->fetch();
        if (!$inq) {
            json_response(false, 'Inquiry not found.', null, [], 404);
        }

        $attStmt = $pdo->prepare("
            SELECT ia.id AS attachment_id, fm.id AS file_metadata_id, fm.original_name, fm.file_size_bytes, fm.mime_type
            FROM inquiry_attachments ia INNER JOIN file_metadata fm ON fm.id = ia.file_metadata_id
            WHERE ia.inquiry_id = ?
        ");
        $attStmt->execute([$id]);
        $inq['attachments'] = $attStmt->fetchAll();

        json_response(true, 'Inquiry loaded.', $inq);
    }

    public function downloadInquiryAttachment(array $params): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            SELECT fm.id FROM inquiry_attachments ia
            INNER JOIN file_metadata fm ON fm.id = ia.file_metadata_id
            WHERE ia.inquiry_id = ? AND ia.id = ? LIMIT 1
        ");
        $stmt->execute([(int) $params['id'], (int) $params['att_id']]);
        $fileId = $stmt->fetchColumn();
        if (!$fileId) {
            json_response(false, 'Attachment not found.', null, [], 404);
        }
        FileService::streamPrivateDownload((int) $fileId);
    }

    public function updateInquiry(array $params): void
    {
        $id = (int) $params['id'];
        $admin = current_user();
        $data = request_json();
        $v = new Validator();
        if (!$v->validate($data, [
            'status' => 'required|in:new,under_review,contacted,qualified,approved,rejected,converted,closed',
            'internal_notes' => 'nullable|max:4000',
        ])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }

        Database::connection()->prepare("
            UPDATE inquiries SET status = ?, internal_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?
        ")->execute([$data['status'], trim((string) ($data['internal_notes'] ?? '')) ?: null, (int) $admin['id'], $id]);

        AuditService::log((int) $admin['id'], 'admin', 'inquiry.updated', 'inquiry', $id, "Updated inquiry #{$id} status to {$data['status']}");
        json_response(true, 'Inquiry updated.');
    }

    public function convertInquiry(array $params): void
    {
        $admin = current_user();
        $res = InquiryService::convertToClientAndProject((int) $params['id'], (int) $admin['id'], request_json());
        if (!$res['ok']) {
            json_response(false, $res['message'], null, [], $res['code']);
        }
        json_response(true, 'Inquiry converted into client and project successfully.', $res, [], 201);
    }

    // --- PROJECTS, ASSIGNMENTS, PROGRESS & MILESTONES ---
    public function projects(): void
    {
        [$page, $perPage, $offset] = pagination_params();
        $status = trim((string) ($_GET['status'] ?? ''));
        $search = trim((string) ($_GET['search'] ?? ''));

        $where = ['1=1'];
        $params = [];
        if ($status !== '') {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[] = '(p.title LIKE ? OR p.project_code LIKE ? OR c.company_name LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $pdo = Database::connection();
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM projects p INNER JOIN clients c ON c.id = p.client_id WHERE " . implode(' AND ', $where));
        $cnt->execute($params);
        $total = (int) $cnt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT p.*, c.company_name, u.full_name AS pm_name
            FROM projects p
            INNER JOIN clients c ON c.id = p.client_id
            LEFT JOIN team_members tm ON tm.id = p.project_manager_id
            LEFT JOIN users u ON u.id = tm.user_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.id DESC LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        json_response(true, 'Projects loaded.', $stmt->fetchAll(), ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)]);
    }

    public function createProject(): void
    {
        $admin = current_user();
        $data = request_json();
        $v = new Validator();
        if (!$v->validate($data, [
            'client_id' => 'required|numeric',
            'title' => 'required|min:3|max:180',
            'description' => 'required|min:5',
            'status' => 'nullable|in:planning,in_progress,on_hold,completed,cancelled,archived',
        ])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }

        $projectCode = 'PRJ-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $pmId = !empty($data['project_manager_id']) ? (int) $data['project_manager_id'] : null;

        $projectId = Database::transaction(static function (\PDO $pdo) use ($data, $admin, $projectCode, $pmId): int {
            $stmt = $pdo->prepare("
                INSERT INTO projects
                (project_code, client_id, service_id, project_manager_id, title, description, scope_of_work, start_date, target_completion_date, status, progress_mode, progress_percentage, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'auto', 0, ?)
            ");
            $stmt->execute([
                $projectCode,
                (int) $data['client_id'],
                !empty($data['service_id']) ? (int) $data['service_id'] : null,
                $pmId,
                trim((string) $data['title']),
                trim((string) $data['description']),
                trim((string) ($data['scope_of_work'] ?? '')) ?: null,
                !empty($data['start_date']) ? $data['start_date'] : date('Y-m-d'),
                !empty($data['target_completion_date']) ? $data['target_completion_date'] : null,
                $data['status'] ?? 'planning',
                (int) $admin['id'],
            ]);
            $pid = (int) $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO project_threads (project_id, subject) VALUES (?, 'General Project Discussion')")->execute([$pid]);
            if ($pmId) {
                $pdo->prepare("INSERT IGNORE INTO project_assignments (project_id, team_member_id, project_role, assigned_by) VALUES (?, ?, 'Project Manager', ?)")->execute([$pid, $pmId, (int) $admin['id']]);
            }
            return $pid;
        });

        AuditService::log((int) $admin['id'], 'admin', 'project.created', 'project', $projectId, "Created project {$projectCode}");
        json_response(true, 'Project created.', ['id' => $projectId, 'project_code' => $projectCode], [], 201);
    }

    public function updateProject(array $params): void
    {
        $pid = (int) $params['id'];
        $admin = current_user();
        $data = request_json();
        $pdo = Database::connection();

        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? LIMIT 1");
        $stmt->execute([$pid]);
        $proj = $stmt->fetch();
        if (!$proj) {
            json_response(false, 'Project not found.', null, [], 404);
        }

        $pdo->prepare("
            UPDATE projects
            SET title = ?, description = ?, scope_of_work = ?, project_manager_id = ?, start_date = ?, target_completion_date = ?, status = ?
            WHERE id = ?
        ")->execute([
            trim((string) ($data['title'] ?? $proj['title'])),
            trim((string) ($data['description'] ?? $proj['description'])),
            array_key_exists('scope_of_work', $data) ? (trim((string) $data['scope_of_work']) ?: null) : $proj['scope_of_work'],
            array_key_exists('project_manager_id', $data) ? (!empty($data['project_manager_id']) ? (int) $data['project_manager_id'] : null) : $proj['project_manager_id'],
            !empty($data['start_date']) ? $data['start_date'] : $proj['start_date'],
            array_key_exists('target_completion_date', $data) ? (!empty($data['target_completion_date']) ? $data['target_completion_date'] : null) : $proj['target_completion_date'],
            $data['status'] ?? $proj['status'],
            $pid,
        ]);

        AuditService::log((int) $admin['id'], 'admin', 'project.updated', 'project', $pid, "Updated project {$proj['project_code']}");
        json_response(true, 'Project updated.');
    }

    public function updateProjectProgress(array $params): void
    {
        $pid = (int) $params['id'];
        $admin = current_user();
        $data = request_json();
        $v = new Validator();
        if (!$v->validate($data, [
            'progress_mode' => 'required|in:auto,manual',
            'progress_percentage' => 'nullable|numeric',
        ])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }

        $result = Database::transaction(static function (\PDO $pdo) use ($pid, $data): array {
            if ($data['progress_mode'] === 'manual') {
                $pct = max(0, min(100, (int) ($data['progress_percentage'] ?? 0)));
                $pdo->prepare("UPDATE projects SET progress_mode = 'manual', progress_percentage = ? WHERE id = ?")->execute([$pct, $pid]);
            } else {
                $pdo->prepare("UPDATE projects SET progress_mode = 'auto' WHERE id = ?")->execute([$pid]);
            }
            return ProgressService::recalculateInTransaction($pdo, $pid);
        });

        AuditService::log((int) $admin['id'], 'admin', 'project.progress_mode_changed', 'project', $pid, "Progress set to {$result['progress_mode']} ({$result['progress_percentage']}%)");
        json_response(true, 'Project progress updated.', $result);
    }

    public function assignTeamMember(array $params): void
    {
        $pid = (int) $params['id'];
        $admin = current_user();
        $data = request_json();
        $tmId = (int) ($data['team_member_id'] ?? 0);
        if ($tmId <= 0) {
            json_response(false, 'Valid team member is required.', null, [], 422);
        }

        $pdo = Database::connection();
        $pdo->prepare("
            INSERT INTO project_assignments (project_id, team_member_id, project_role, assigned_by)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE project_role = VALUES(project_role)
        ")->execute([$pid, $tmId, trim((string) ($data['project_role'] ?? 'Contributor')) ?: 'Contributor', (int) $admin['id']]);

        $tmUser = $pdo->prepare("SELECT u.id, u.email, u.full_name, p.title, p.project_code FROM team_members tm INNER JOIN users u ON u.id = tm.user_id INNER JOIN projects p ON p.id = ? WHERE tm.id = ?");
        $tmUser->execute([$pid, $tmId]);
        if ($row = $tmUser->fetch()) {
            NotificationService::notifyUser((int) $row['id'], 'project_assigned', "Assigned to {$row['project_code']}", "You were assigned to {$row['title']}.", 'project', $pid, '/admin/workspace/projects/' . $pid);
            EmailQueueService::enqueue($row['email'], 'project_assigned', [
                'full_name' => $row['full_name'],
                'project_title' => $row['title'],
                'project_code' => $row['project_code'],
                'project_role' => $data['project_role'] ?? 'Contributor',
                'action_url' => App::config('app.url') . '/admin/workspace/projects/' . $pid,
            ], "assign:{$pid}:{$tmId}");
        }

        AuditService::log((int) $admin['id'], 'admin', 'project.team_assigned', 'project', $pid, "Assigned team member #{$tmId} to project #{$pid}");
        json_response(true, 'Team member assigned.');
    }

    public function removeTeamAssignment(array $params): void
    {
        $pid = (int) $params['id'];
        $tmId = (int) $params['team_id'];
        $admin = current_user();
        Database::connection()->prepare("DELETE FROM project_assignments WHERE project_id = ? AND team_member_id = ?")->execute([$pid, $tmId]);
        AuditService::log((int) $admin['id'], 'admin', 'project.team_removed', 'project', $pid, "Removed team member #{$tmId} from project #{$pid}");
        json_response(true, 'Team assignment removed.');
    }

    public function createMilestone(array $params): void
    {
        $pid = (int) $params['id'];
        $admin = current_user();
        $data = request_json();
        $v = new Validator();
        if (!$v->validate($data, ['title' => 'required|min:2|max:160', 'planned_date' => 'nullable|date', 'status' => 'nullable|in:pending,in_progress,completed,delayed'])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("INSERT INTO project_milestones (project_id, title, description, planned_date, status, display_order, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $pid,
            trim((string) $data['title']),
            trim((string) ($data['description'] ?? '')) ?: null,
            !empty($data['planned_date']) ? $data['planned_date'] : null,
            $data['status'] ?? 'pending',
            (int) ($data['display_order'] ?? 0),
            (int) $admin['id'],
        ]);
        $msId = (int) $pdo->lastInsertId();
        AuditService::log((int) $admin['id'], 'admin', 'milestone.created', 'project_milestone', $msId, "Created milestone '{$data['title']}' on project #{$pid}");
        json_response(true, 'Milestone created.', ['id' => $msId], [], 201);
    }

    public function updateMilestone(array $params): void
    {
        $msId = (int) $params['ms_id'];
        $admin = current_user();
        $data = request_json();
        $pdo = Database::connection();

        $stmt = $pdo->prepare("SELECT * FROM project_milestones WHERE id = ? LIMIT 1");
        $stmt->execute([$msId]);
        $ms = $stmt->fetch();
        if (!$ms) {
            json_response(false, 'Milestone not found.', null, [], 404);
        }

        $status = $data['status'] ?? $ms['status'];
        $compDate = $status === 'completed' ? ($data['completion_date'] ?? date('Y-m-d')) : null;
        $pdo->prepare("UPDATE project_milestones SET title = ?, description = ?, planned_date = ?, completion_date = ?, status = ? WHERE id = ?")
            ->execute([
                trim((string) ($data['title'] ?? $ms['title'])),
                array_key_exists('description', $data) ? (trim((string) $data['description']) ?: null) : $ms['description'],
                array_key_exists('planned_date', $data) ? (!empty($data['planned_date']) ? $data['planned_date'] : null) : $ms['planned_date'],
                $compDate,
                $status,
                $msId,
            ]);

        AuditService::log((int) $admin['id'], 'admin', 'milestone.updated', 'project_milestone', $msId, "Updated milestone #{$msId} -> {$status}");
        json_response(true, 'Milestone updated.');
    }

    public function deleteMilestone(array $params): void
    {
        $msId = (int) $params['ms_id'];
        $admin = current_user();
        Database::connection()->prepare("DELETE FROM project_milestones WHERE id = ?")->execute([$msId]);
        AuditService::log((int) $admin['id'], 'admin', 'milestone.deleted', 'project_milestone', $msId, "Deleted milestone #{$msId}");
        json_response(true, 'Milestone deleted.');
    }

    // --- ADMIN DOCUMENT & DELIVERABLE PUBLICATION ---
    public function publishDocument(array $params): void
    {
        $docId = (int) $params['doc_id'];
        $admin = current_user();
        $pdo = Database::connection();

        $stmt = $pdo->prepare("SELECT * FROM project_documents WHERE id = ? LIMIT 1");
        $stmt->execute([$docId]);
        $doc = $stmt->fetch();
        if (!$doc) {
            json_response(false, 'Document not found.', null, [], 404);
        }

        $pdo->prepare("
            UPDATE project_documents
            SET folder_area = 'agency_shared', is_client_visible = 1, review_status = 'reviewed', reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ?
        ")->execute([(int) $admin['id'], $docId]);

        AuditService::log((int) $admin['id'], 'admin', 'document.published', 'project_document', $docId, "Published document '{$doc['title']}' to client");
        json_response(true, 'Document published to Agency Shared Documents.');
    }

    public function unpublishDocument(array $params): void
    {
        $docId = (int) $params['doc_id'];
        $admin = current_user();
        Database::connection()->prepare("UPDATE project_documents SET folder_area = 'internal_only', is_client_visible = 0 WHERE id = ?")->execute([$docId]);
        AuditService::log((int) $admin['id'], 'admin', 'document.unpublished', 'project_document', $docId, "Unpublished document #{$docId}");
        json_response(true, 'Document moved to internal-only.');
    }

    public function deleteDocument(array $params): void
    {
        $docId = (int) $params['doc_id'];
        $admin = current_user();
        $pdo = Database::connection();
        $fileId = $pdo->query("SELECT file_metadata_id FROM project_documents WHERE id = {$docId}")->fetchColumn();
        $pdo->prepare("DELETE FROM project_documents WHERE id = ?")->execute([$docId]);
        if ($fileId) {
            FileService::deleteIfUnreferenced((int) $fileId);
        }
        AuditService::log((int) $admin['id'], 'admin', 'document.deleted', 'project_document', $docId, "Deleted document #{$docId}");
        json_response(true, 'Document deleted.');
    }

    public function publishDeliverable(array $params): void
    {
        $delivId = (int) $params['id'];
        $admin = current_user();
        $data = request_json();

        $res = Database::transaction(static function (\PDO $pdo) use ($delivId, $admin, $data): array {
            $stmt = $pdo->prepare("
                SELECT d.*, p.title AS project_title, p.project_code, u.id AS client_user_id, u.email AS client_email, u.full_name AS client_name
                FROM project_deliverables d
                INNER JOIN projects p ON p.id = d.project_id
                INNER JOIN clients c ON c.id = p.client_id
                INNER JOIN users u ON u.id = c.user_id
                WHERE d.id = ? LIMIT 1 FOR UPDATE
            ");
            $stmt->execute([$delivId]);
            $deliv = $stmt->fetch();
            if (!$deliv) {
                return ['ok' => false, 'code' => 404, 'message' => 'Deliverable not found.'];
            }

            $pdo->prepare("
                UPDATE project_deliverables
                SET review_status = 'approved_published', is_published_to_client = 1, published_by = ?, published_at = NOW()
                WHERE id = ?
            ")->execute([(int) $admin['id'], $delivId]);

            $pdo->prepare("INSERT INTO deliverable_review_history (deliverable_id, action, acted_by, notes) VALUES (?, 'approved_published', ?, ?)")
                ->execute([$delivId, (int) $admin['id'], trim((string) ($data['notes'] ?? 'Approved & published'))]);

            return ['ok' => true, 'deliverable' => $deliv];
        });

        if (!$res['ok']) {
            json_response(false, $res['message'], null, [], $res['code']);
        }

        $d = $res['deliverable'];
        AuditService::log((int) $admin['id'], 'admin', 'deliverable.published', 'project_deliverable', $delivId, "Published deliverable '{$d['title']}' to client");
        NotificationService::notifyUser((int) $d['client_user_id'], 'deliverable_published', "Deliverable Published: {$d['title']}", "New official deliverable for {$d['project_code']} is ready for download.", 'project', (int) $d['project_id'], '/portal/projects/' . $d['project_id']);
        EmailQueueService::enqueue($d['client_email'], 'deliverable_published', [
            'full_name' => $d['client_name'],
            'deliverable_title' => $d['title'],
            'version_label' => $d['version_label'],
            'action_url' => App::config('app.url') . '/portal/projects/' . $d['project_id'],
        ], "deliverable_pub:{$delivId}");

        json_response(true, 'Deliverable published and client notified.');
    }

    public function rejectDeliverable(array $params): void
    {
        $delivId = (int) $params['id'];
        $admin = current_user();
        $data = request_json();
        $notes = trim((string) ($data['notes'] ?? ''));
        if ($notes === '') {
            json_response(false, 'Rejection feedback notes are required.', null, [], 422);
        }

        Database::transaction(static function (\PDO $pdo) use ($delivId, $admin, $notes): void {
            $pdo->prepare("UPDATE project_deliverables SET review_status = 'rejected', is_published_to_client = 0 WHERE id = ?")->execute([$delivId]);
            $pdo->prepare("INSERT INTO deliverable_review_history (deliverable_id, action, acted_by, notes) VALUES (?, 'rejected', ?, ?)")->execute([$delivId, (int) $admin['id'], $notes]);
        });

        AuditService::log((int) $admin['id'], 'admin', 'deliverable.rejected', 'project_deliverable', $delivId, "Rejected deliverable #{$delivId}");
        json_response(true, 'Deliverable marked as rejected with feedback.');
    }

    // --- INVOICES & PAYMENTS ---
    public function invoices(): void
    {
        [$page, $perPage, $offset] = pagination_params();
        $status = trim((string) ($_GET['status'] ?? ''));
        $search = trim((string) ($_GET['search'] ?? ''));

        $where = ['1=1'];
        $params = [];
        if ($status !== '') {
            if ($status === 'overdue') {
                $where[] = "i.status IN ('issued','partially_paid') AND i.due_date < CURDATE() AND i.remaining_balance > 0";
            } else {
                $where[] = 'i.status = ?';
                $params[] = $status;
            }
        }
        if ($search !== '') {
            $where[] = '(i.invoice_number LIKE ? OR c.company_name LIKE ? OR p.project_code LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $pdo = Database::connection();
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM invoices i INNER JOIN clients c ON c.id = i.client_id INNER JOIN projects p ON p.id = i.project_id WHERE " . implode(' AND ', $where));
        $cnt->execute($params);
        $total = (int) $cnt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT i.*, c.company_name, p.project_code, p.title AS project_title
            FROM invoices i
            INNER JOIN clients c ON c.id = i.client_id
            INNER JOIN projects p ON p.id = i.project_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY i.id DESC LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $rows = array_map(static function (array $inv): array {
            $inv['is_overdue'] = InvoiceService::isOverdue($inv);
            $inv['formatted_total'] = format_currency($inv['total_amount'], $inv['currency_code']);
            $inv['formatted_paid'] = format_currency($inv['amount_paid'], $inv['currency_code']);
            $inv['formatted_balance'] = format_currency($inv['remaining_balance'], $inv['currency_code']);
            return $inv;
        }, $stmt->fetchAll());

        json_response(true, 'Invoices loaded.', $rows, ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)]);
    }

    public function invoiceDetail(array $params): void
    {
        $id = (int) $params['id'];
        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            SELECT i.*, c.company_name, c.billing_address, c.tax_id, u.full_name AS client_name, u.email AS client_email,
                   p.project_code, p.title AS project_title
            FROM invoices i
            INNER JOIN clients c ON c.id = i.client_id
            INNER JOIN users u ON u.id = c.user_id
            INNER JOIN projects p ON p.id = i.project_id
            WHERE i.id = ? LIMIT 1
        ");
        $stmt->execute([$id]);
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
        $items->execute([$id]);
        $itemRows = array_map(static function (array $it) use ($inv): array {
            $it['formatted_unit_price'] = format_currency($it['unit_price'], $inv['currency_code']);
            $it['formatted_line_total'] = format_currency($it['line_total'], $inv['currency_code']);
            return $it;
        }, $items->fetchAll());

        $pays = $pdo->prepare("
            SELECT pay.*, ru.full_name AS recorded_by_name, vu.full_name AS voided_by_name
            FROM payments pay
            INNER JOIN users ru ON ru.id = pay.recorded_by
            LEFT JOIN users vu ON vu.id = pay.voided_by
            WHERE pay.invoice_id = ?
            ORDER BY pay.created_at DESC
        ");
        $pays->execute([$id]);
        $payRows = array_map(static function (array $pm) use ($inv): array {
            $pm['formatted_amount'] = format_currency($pm['amount'], $inv['currency_code']);
            return $pm;
        }, $pays->fetchAll());

        json_response(true, 'Invoice loaded.', ['invoice' => $inv, 'items' => $itemRows, 'payments' => $payRows]);
    }

    public function createInvoice(): void
    {
        $res = InvoiceService::createDraft((int) current_user()['id'], request_json());
        if (!$res['ok']) {
            json_response(false, $res['message'], null, [], $res['code']);
        }
        json_response(true, 'Draft invoice created.', $res, [], 201);
    }

    public function updateInvoice(array $params): void
    {
        $res = InvoiceService::updateDraft((int) $params['id'], (int) current_user()['id'], request_json());
        if (!$res['ok']) {
            json_response(false, $res['message'], null, [], $res['code']);
        }
        json_response(true, $res['message']);
    }

    public function issueInvoice(array $params): void
    {
        $res = InvoiceService::issueInvoice((int) $params['id'], (int) current_user()['id']);
        if (!$res['ok']) {
            json_response(false, $res['message'], null, [], $res['code']);
        }
        json_response(true, $res['message']);
    }

    public function cancelInvoice(array $params): void
    {
        $res = InvoiceService::cancelInvoice((int) $params['id'], (int) current_user()['id']);
        if (!$res['ok']) {
            json_response(false, $res['message'], null, [], $res['code']);
        }
        json_response(true, $res['message']);
    }

    public function recordPayment(array $params): void
    {
        $data = request_json();
        $v = new Validator();
        if (!$v->validate($data, [
            'amount' => 'required|positive',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:bank_transfer,upi,neft_rtgs,wire,check,cash,card_offline,other',
        ])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }

        $res = InvoiceService::recordPayment((int) $params['id'], (int) current_user()['id'], $data);
        if (!$res['ok']) {
            json_response(false, $res['message'], null, [], $res['code']);
        }
        json_response(true, $res['message'], $res['data'], [], 201);
    }

    public function voidPayment(array $params): void
    {
        $data = request_json();
        $reason = trim((string) ($data['void_reason'] ?? ''));
        if (mb_strlen($reason) < 5) {
            json_response(false, 'A reason of at least 5 characters is required to void a payment.', null, [], 422);
        }

        $res = InvoiceService::voidPayment((int) $params['id'], (int) current_user()['id'], $reason);
        if (!$res['ok']) {
            json_response(false, $res['message'], null, [], $res['code']);
        }
        json_response(true, $res['message'], $res['data']);
    }

    public function downloadInvoicePdf(array $params): void
    {
        $id = (int) $params['id'];
        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            SELECT i.*, c.company_name, c.billing_address, c.tax_id, u.full_name AS client_name, u.email AS client_email, p.title AS project_title, p.project_code
            FROM invoices i INNER JOIN clients c ON c.id = i.client_id INNER JOIN users u ON u.id = c.user_id INNER JOIN projects p ON p.id = i.project_id
            WHERE i.id = ? LIMIT 1
        ");
        $stmt->execute([$id]);
        $inv = $stmt->fetch();
        if (!$inv) {
            json_response(false, 'Invoice not found.', null, [], 404);
        }
        if ($inv['pdf_file_metadata_id']) {
            FileService::streamPrivateDownload((int) $inv['pdf_file_metadata_id'], $inv['invoice_number'] . '.pdf');
        }
        $items = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY display_order ASC");
        $items->execute([$id]);
        $pays = $pdo->prepare("SELECT * FROM payments WHERE invoice_id = ? AND status = 'recorded' ORDER BY payment_date ASC");
        $pays->execute([$id]);
        $pdfId = PdfService::generateInvoicePdf($inv, $items->fetchAll(), $pays->fetchAll(), (int) current_user()['id']);
        $pdo->prepare("UPDATE invoices SET pdf_file_metadata_id = ? WHERE id = ?")->execute([$pdfId, $id]);
        FileService::streamPrivateDownload($pdfId, $inv['invoice_number'] . '.pdf');
    }

    // --- TEAM & USERS ---
    public function teamMembers(): void
    {
        $rows = Database::connection()->query("
            SELECT tm.*, u.email, u.full_name, u.phone, u.status AS user_status,
                   (SELECT COUNT(*) FROM project_assignments pa WHERE pa.team_member_id = tm.id) AS assigned_projects
            FROM team_members tm INNER JOIN users u ON u.id = tm.user_id
            ORDER BY tm.display_order ASC, tm.id DESC
        ")->fetchAll();
        json_response(true, 'Team members loaded.', $rows);
    }

    public function createTeamMember(): void
    {
        $admin = current_user();
        $data = request_json();
        $v = new Validator();
        if (!$v->validate($data, [
            'full_name' => 'required|min:2|max:120',
            'email' => 'required|email|max:190',
            'designation' => 'required|min:2|max:120',
            'password' => 'required|strong_password',
        ])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }

        $email = strtolower(trim((string) $data['email']));
        $pdo = Database::connection();
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $chk->execute([$email]);
        if ($chk->fetchColumn()) {
            json_response(false, 'Email already exists.', null, [], 409);
        }

        $tmId = Database::transaction(static function (\PDO $pdo) use ($data, $email): int {
            $roleId = (int) $pdo->query("SELECT id FROM roles WHERE slug = 'team_member' LIMIT 1")->fetchColumn();
            $uIns = $pdo->prepare("INSERT INTO users (role_id, email, password_hash, full_name, phone, status, email_verified_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
            $uIns->execute([$roleId, $email, AuthService::hashPassword((string) $data['password']), trim((string) $data['full_name']), trim((string) ($data['phone'] ?? '')) ?: null]);
            $uid = (int) $pdo->lastInsertId();

            $skills = is_array($data['skills'] ?? null) ? $data['skills'] : array_values(array_filter(array_map('trim', explode(',', (string) ($data['skills'] ?? '')))));
            $tIns = $pdo->prepare("INSERT INTO team_members (user_id, designation, bio, skills_json, show_on_public_website, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $tIns->execute([$uid, trim((string) $data['designation']), trim((string) ($data['bio'] ?? '')) ?: null, json_encode($skills), !empty($data['show_on_public_website']) ? 1 : 0]);
            return (int) $pdo->lastInsertId();
        });

        AuditService::log((int) $admin['id'], 'admin', 'team.created', 'team_member', $tmId, "Created team member {$data['full_name']}");
        json_response(true, 'Team member created.', ['id' => $tmId], [], 201);
    }

    public function updateTeamMember(array $params): void
    {
        $tmId = (int) $params['id'];
        $admin = current_user();
        $data = request_json();
        $pdo = Database::connection();

        $stmt = $pdo->prepare("SELECT tm.*, u.full_name, u.phone FROM team_members tm INNER JOIN users u ON u.id = tm.user_id WHERE tm.id = ? LIMIT 1");
        $stmt->execute([$tmId]);
        $tm = $stmt->fetch();
        if (!$tm) {
            json_response(false, 'Team member not found.', null, [], 404);
        }

        $status = ($data['status'] ?? $tm['status']) === 'inactive' ? 'inactive' : 'active';
        $skills = isset($data['skills'])
            ? (is_array($data['skills']) ? json_encode($data['skills']) : json_encode(array_values(array_filter(array_map('trim', explode(',', (string) $data['skills']))))))
            : $tm['skills_json'];

        Database::transaction(static function (\PDO $pdo) use ($tmId, $tm, $data, $status, $skills): void {
            $pdo->prepare("UPDATE team_members SET designation = ?, bio = ?, skills_json = ?, show_on_public_website = ?, status = ? WHERE id = ?")
                ->execute([
                    trim((string) ($data['designation'] ?? $tm['designation'])),
                    array_key_exists('bio', $data) ? (trim((string) $data['bio']) ?: null) : $tm['bio'],
                    $skills,
                    isset($data['show_on_public_website']) ? ((int) $data['show_on_public_website'] ? 1 : 0) : (int) $tm['show_on_public_website'],
                    $status,
                    $tmId,
                ]);
            $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, status = ?, token_version = token_version + 1 WHERE id = ?")
                ->execute([
                    trim((string) ($data['full_name'] ?? $tm['full_name'])),
                    array_key_exists('phone', $data) ? (trim((string) $data['phone']) ?: null) : $tm['phone'],
                    $status,
                    (int) $tm['user_id'],
                ]);
        });

        AuditService::log((int) $admin['id'], 'admin', 'team.updated', 'team_member', $tmId, "Updated team member #{$tmId} (status={$status})");
        json_response(true, 'Team member updated.');
    }

    public function users(): void
    {
        $rows = Database::connection()->query("
            SELECT u.id, u.email, u.full_name, u.phone, u.status, u.last_login_at, u.created_at, r.id AS role_id, r.slug AS role_slug, r.name AS role_name
            FROM users u INNER JOIN roles r ON r.id = u.role_id
            ORDER BY u.id DESC
        ")->fetchAll();
        json_response(true, 'Users loaded.', $rows);
    }

    public function updateUser(array $params): void
    {
        $uid = (int) $params['id'];
        $admin = current_user();
        $data = request_json();

        if ($uid === (int) $admin['id'] && (($data['status'] ?? 'active') !== 'active')) {
            json_response(false, 'You cannot deactivate your own active administrator account.', null, [], 422);
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$uid]);
        $u = $stmt->fetch();
        if (!$u) {
            json_response(false, 'User not found.', null, [], 404);
        }

        $roleId = (int) ($data['role_id'] ?? $u['role_id']);
        $status = in_array($data['status'] ?? $u['status'], ['active', 'inactive', 'locked', 'pending_activation'], true) ? ($data['status'] ?? $u['status']) : $u['status'];

        $pdo->prepare("UPDATE users SET role_id = ?, status = ?, token_version = token_version + 1 WHERE id = ?")
            ->execute([$roleId, $status, $uid]);

        AuditService::log((int) $admin['id'], 'admin', 'user.updated', 'user', $uid, "Updated user #{$uid} (role_id={$roleId}, status={$status}) & invalidated active sessions");
        json_response(true, 'User updated and active sessions invalidated.');
    }

    // --- SETTINGS, EMAIL QUEUE & LOGS ---
    public function settings(): void
    {
        $settings = app_settings_all();
        $pages = Database::connection()->query("SELECT id, page_key, title, content_body, structured_sections_json, updated_at FROM cms_pages ORDER BY id ASC")->fetchAll();
        json_response(true, 'Settings loaded.', ['settings' => $settings, 'cms_pages' => $pages]);
    }

    public function updateSettings(): void
    {
        $admin = current_user();
        $data = request_json();
        $allowedKeys = [
            'site.name', 'site.tagline', 'site.email', 'site.phone', 'site.address',
            'site.footer_note', 'site.social_linkedin', 'site.social_github', 'site.social_dribbble',
            'seo.default_title', 'seo.default_description',
            'finance.default_currency', 'finance.default_tax_rate', 'finance.tax_label', 'finance.invoice_prefix', 'finance.bank_details',
            'email.reply_to', 'system.enable_blog', 'system.maintenance_mode',
        ];
        $stmt = Database::connection()->prepare("UPDATE app_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?");
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $data)) {
                $stmt->execute([trim((string) $data[$key]), (int) $admin['id'], $key]);
            }
        }
        AuditService::log((int) $admin['id'], 'admin', 'settings.updated', 'app_settings', null, 'Updated website/finance/system settings');
        json_response(true, 'Settings saved.');
    }

    public function emailTemplates(): void
    {
        $rows = Database::connection()->query("SELECT * FROM email_templates ORDER BY id ASC")->fetchAll();
        json_response(true, 'Email templates loaded.', $rows);
    }

    public function updateEmailTemplate(array $params): void
    {
        $id = (int) $params['id'];
        $admin = current_user();
        $data = request_json();
        $v = new Validator();
        if (!$v->validate($data, ['subject_template' => 'required|max:255', 'body_html_template' => 'required'])) {
            json_response(false, 'Validation failed.', null, [], 422, $v->errors());
        }
        Database::connection()->prepare("UPDATE email_templates SET subject_template = ?, body_html_template = ?, is_active = ?, updated_by = ? WHERE id = ?")
            ->execute([trim((string) $data['subject_template']), (string) $data['body_html_template'], !empty($data['is_active']) ? 1 : 0, (int) $admin['id'], $id]);
        AuditService::log((int) $admin['id'], 'admin', 'email_template.updated', 'email_template', $id, "Updated email template #{$id}");
        json_response(true, 'Email template updated.');
    }

    public function emailQueue(): void
    {
        [$page, $perPage, $offset] = pagination_params(25);
        $status = trim((string) ($_GET['status'] ?? ''));
        $where = $status !== '' ? 'status = ?' : '1=1';
        $params = $status !== '' ? [$status] : [];
        $pdo = Database::connection();
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM email_queue_logs WHERE {$where}");
        $cnt->execute($params);
        $total = (int) $cnt->fetchColumn();

        $stmt = $pdo->prepare("SELECT * FROM email_queue_logs WHERE {$where} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($params);
        json_response(true, 'Email queue loaded.', $stmt->fetchAll(), ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)]);
    }

    public function processEmailQueue(): void
    {
        $summary = EmailQueueService::processDueJobs(25);
        AuditService::log((int) current_user()['id'], 'admin', 'email_queue.manual_run', 'email_queue', null, "Manual email queue run: sent={$summary['sent']}, failed={$summary['failed']}");
        json_response(true, "Queue processed: {$summary['sent']} sent, {$summary['failed']} failed.", $summary);
    }

    public function retryEmailJob(array $params): void
    {
        $jobId = (int) $params['id'];
        Database::connection()->prepare("UPDATE email_queue_logs SET status = 'failed', attempts = 0, next_retry_at = NOW(), lease_expires_at = NULL WHERE id = ? AND status IN ('failed','permanently_failed')")
            ->execute([$jobId]);
        $sent = EmailQueueService::dispatchJob($jobId);
        json_response($sent, $sent ? 'Email dispatched successfully.' : 'Retry attempted; check error log.');
    }

    public function activityLogs(): void
    {
        [$page, $perPage, $offset] = pagination_params(30);
        $action = trim((string) ($_GET['action'] ?? ''));
        $where = $action !== '' ? 'a.action LIKE ?' : '1=1';
        $params = $action !== '' ? ["%{$action}%"] : [];

        $pdo = Database::connection();
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs a WHERE {$where}");
        $cnt->execute($params);
        $total = (int) $cnt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT a.*, u.full_name AS actor_name, u.email AS actor_email
            FROM activity_logs a LEFT JOIN users u ON u.id = a.actor_user_id
            WHERE {$where}
            ORDER BY a.id DESC LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        json_response(true, 'Activity logs loaded.', $stmt->fetchAll(), ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)]);
    }
}
