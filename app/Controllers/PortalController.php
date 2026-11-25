<?php
declare(strict_types=1);

namespace Orixo\Controllers;

use Orixo\Bootstrap\Database;
use Orixo\Middleware\ProjectAccessMiddleware;

/**
 * Renders Client Portal pages (enforces role = client + strict client_id isolation).
 */
final class PortalController
{
    public function dashboard(): void
    {
        render_view('portal/dashboard', [
            'pageTitle' => 'Client Dashboard | Orixo Portal',
            'activeNav' => 'dashboard',
        ], 'layouts/portal_layout');
    }

    public function projects(): void
    {
        render_view('portal/projects', [
            'pageTitle' => 'My Projects | Orixo Portal',
            'activeNav' => 'projects',
        ], 'layouts/portal_layout');
    }

    public function projectDetail(array $params): void
    {
        $project = ProjectAccessMiddleware::assertCanAccessProject((int) $params['id']);
        render_view('portal/project_detail', [
            'pageTitle' => $project['title'] . ' | Orixo Portal',
            'activeNav' => 'projects',
            'projectId' => (int) $project['id'],
            'project' => $project,
        ], 'layouts/portal_layout');
    }

    public function deliverables(): void
    {
        render_view('portal/deliverables', [
            'pageTitle' => 'Deliverables | Orixo Portal',
            'activeNav' => 'deliverables',
        ], 'layouts/portal_layout');
    }

    public function documents(): void
    {
        render_view('portal/documents', [
            'pageTitle' => 'Documents & Client Uploads | Orixo Portal',
            'activeNav' => 'documents',
        ], 'layouts/portal_layout');
    }

    public function messages(): void
    {
        render_view('portal/messages', [
            'pageTitle' => 'Project Communication | Orixo Portal',
            'activeNav' => 'messages',
        ], 'layouts/portal_layout');
    }

    public function invoices(): void
    {
        render_view('portal/invoices', [
            'pageTitle' => 'Invoices & Payments | Orixo Portal',
            'activeNav' => 'invoices',
        ], 'layouts/portal_layout');
    }

    public function invoiceDetail(array $params): void
    {
        $user = current_user();
        $stmt = Database::connection()->prepare("
            SELECT id FROM invoices WHERE id = ? AND client_id = ? AND status != 'draft' LIMIT 1
        ");
        $stmt->execute([(int) $params['id'], (int) $user['client_id']]);
        if (!$stmt->fetchColumn()) {
            http_response_code(404);
            render_view('public/404', ['pageTitle' => 'Invoice Not Found | Orixo'], 'layouts/portal_layout');
            return;
        }
        render_view('portal/invoice_detail', [
            'pageTitle' => 'Invoice Details | Orixo Portal',
            'activeNav' => 'invoices',
            'invoiceId' => (int) $params['id'],
        ], 'layouts/portal_layout');
    }

    public function notifications(): void
    {
        render_view('portal/notifications', [
            'pageTitle' => 'Notifications | Orixo Portal',
            'activeNav' => 'notifications',
        ], 'layouts/portal_layout');
    }

    public function profile(): void
    {
        render_view('portal/profile', [
            'pageTitle' => 'Profile & Security | Orixo Portal',
            'activeNav' => 'profile',
        ], 'layouts/portal_layout');
    }
}
