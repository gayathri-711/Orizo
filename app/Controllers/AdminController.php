<?php
declare(strict_types=1);

namespace Orixo\Controllers;

use Orixo\Bootstrap\Database;
use Orixo\Middleware\ProjectAccessMiddleware;

/**
 * Renders Admin Dashboard (role = admin) and Restricted Team Workspace (role = team_member).
 */
final class AdminController
{
    public function dashboard(): void
    {
        render_view('admin/dashboard', [
            'pageTitle' => 'Admin Dashboard | Orixo Operations',
            'activeNav' => 'dashboard',
            'isTeamWorkspace' => false,
        ], 'layouts/admin_layout');
    }

    public function clients(): void
    {
        render_view('admin/clients', [
            'pageTitle' => 'Client Management | Orixo Admin',
            'activeNav' => 'clients',
            'isTeamWorkspace' => false,
        ], 'layouts/admin_layout');
    }

    public function inquiries(): void
    {
        $services = Database::connection()->query("SELECT id, title FROM services ORDER BY title ASC")->fetchAll();
        $team = Database::connection()->query("
            SELECT tm.id, u.full_name, tm.designation
            FROM team_members tm INNER JOIN users u ON u.id = tm.user_id
            WHERE tm.status = 'active' ORDER BY u.full_name ASC
        ")->fetchAll();

        render_view('admin/inquiries', [
            'pageTitle' => 'Inquiry & Contact Inbox | Orixo Admin',
            'activeNav' => 'inquiries',
            'services' => $services,
            'teamMembers' => $team,
            'isTeamWorkspace' => false,
        ], 'layouts/admin_layout');
    }

    public function projects(): void
    {
        $clients = Database::connection()->query("
            SELECT c.id, c.company_name, u.full_name
            FROM clients c INNER JOIN users u ON u.id = c.user_id
            WHERE c.status = 'active' ORDER BY c.company_name ASC
        ")->fetchAll();
        $services = Database::connection()->query("SELECT id, title FROM services ORDER BY title ASC")->fetchAll();
        $team = Database::connection()->query("
            SELECT tm.id, u.full_name, tm.designation
            FROM team_members tm INNER JOIN users u ON u.id = tm.user_id
            WHERE tm.status = 'active' ORDER BY u.full_name ASC
        ")->fetchAll();

        render_view('admin/projects', [
            'pageTitle' => 'Project Management | Orixo Admin',
            'activeNav' => 'projects',
            'clients' => $clients,
            'services' => $services,
            'teamMembers' => $team,
            'isTeamWorkspace' => false,
        ], 'layouts/admin_layout');
    }

    public function projectDetail(array $params): void
    {
        $project = ProjectAccessMiddleware::assertCanAccessProject((int) $params['id']);
        $team = Database::connection()->query("
            SELECT tm.id, u.full_name, tm.designation
            FROM team_members tm INNER JOIN users u ON u.id = tm.user_id
            WHERE tm.status = 'active' ORDER BY u.full_name ASC
        ")->fetchAll();

        render_view('admin/project_detail', [
            'pageTitle' => $project['title'] . ' | Orixo Admin',
            'activeNav' => 'projects',
            'projectId' => (int) $project['id'],
            'project' => $project,
            'teamMembers' => $team,
            'isTeamWorkspace' => false,
        ], 'layouts/admin_layout');
    }

    public function team(): void
    {
        render_view('admin/team', [
            'pageTitle' => 'Team Management | Orixo Admin',
            'activeNav' => 'team',
            'isTeamWorkspace' => false,
        ], 'layouts/admin_layout');
    }

    public function invoices(): void
    {
        $clients = Database::connection()->query("
            SELECT c.id, c.company_name, u.full_name
            FROM clients c INNER JOIN users u ON u.id = c.user_id
            ORDER BY c.company_name ASC
        ")->fetchAll();
        $projects = Database::connection()->query("SELECT id, client_id, project_code, title FROM projects ORDER BY id DESC")->fetchAll();

        render_view('admin/invoices', [
            'pageTitle' => 'Invoices & Payments | Orixo Admin',
            'activeNav' => 'invoices',
            'clients' => $clients,
            'projects' => $projects,
            'isTeamWorkspace' => false,
        ], 'layouts/admin_layout');
    }

    public function invoiceDetail(array $params): void
    {
        render_view('admin/invoice_detail', [
            'pageTitle' => 'Invoice Detail | Orixo Admin',
            'activeNav' => 'invoices',
            'invoiceId' => (int) $params['id'],
            'isTeamWorkspace' => false,
        ], 'layouts/admin_layout');
    }

    public function cms(): void
    {
        render_view('admin/cms', [
            'pageTitle' => 'CMS Content Management | Orixo Admin',
            'activeNav' => 'cms',
            'isTeamWorkspace' => false,
        ], 'layouts/admin_layout');
    }

    public function users(): void
    {
        $roles = Database::connection()->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();
        render_view('admin/users', [
            'pageTitle' => 'Users & Roles | Orixo Admin',
            'activeNav' => 'users',
            'roles' => $roles,
            'isTeamWorkspace' => false,
        ], 'layouts/admin_layout');
    }

    public function settings(): void
    {
        render_view('admin/settings', [
            'pageTitle' => 'Website & Email Settings | Orixo Admin',
            'activeNav' => 'settings',
            'isTeamWorkspace' => false,
        ], 'layouts/admin_layout');
    }

    public function emailQueue(): void
    {
        render_view('admin/email_queue', [
            'pageTitle' => 'Email Queue & Templates | Orixo Admin',
            'activeNav' => 'email_queue',
            'isTeamWorkspace' => false,
        ], 'layouts/admin_layout');
    }

    public function activityLogs(): void
    {
        render_view('admin/activity_logs', [
            'pageTitle' => 'Activity & Audit Logs | Orixo Admin',
            'activeNav' => 'activity_logs',
            'isTeamWorkspace' => false,
        ], 'layouts/admin_layout');
    }

    // --- Restricted Team Workspace Views ---

    public function workspaceDashboard(): void
    {
        render_view('admin/workspace_dashboard', [
            'pageTitle' => 'Team Workspace | Orixo',
            'activeNav' => 'ws_dashboard',
            'isTeamWorkspace' => true,
        ], 'layouts/admin_layout');
    }

    public function workspaceProjects(): void
    {
        render_view('admin/workspace_projects', [
            'pageTitle' => 'Assigned Projects | Team Workspace',
            'activeNav' => 'ws_projects',
            'isTeamWorkspace' => true,
        ], 'layouts/admin_layout');
    }

    public function workspaceProjectDetail(array $params): void
    {
        $project = ProjectAccessMiddleware::assertCanAccessProject((int) $params['id']);
        $team = Database::connection()->query("
            SELECT tm.id, u.full_name, tm.designation
            FROM project_assignments pa
            INNER JOIN team_members tm ON tm.id = pa.team_member_id
            INNER JOIN users u ON u.id = tm.user_id
            WHERE pa.project_id = " . (int) $project['id'] . " AND tm.status = 'active'
        ")->fetchAll();

        render_view('admin/project_detail', [
            'pageTitle' => $project['title'] . ' | Team Workspace',
            'activeNav' => 'ws_projects',
            'projectId' => (int) $project['id'],
            'project' => $project,
            'teamMembers' => $team,
            'isTeamWorkspace' => true,
        ], 'layouts/admin_layout');
    }

    public function workspaceProfile(): void
    {
        render_view('admin/workspace_profile', [
            'pageTitle' => 'My Profile | Team Workspace',
            'activeNav' => 'ws_profile',
            'isTeamWorkspace' => true,
        ], 'layouts/admin_layout');
    }
}
