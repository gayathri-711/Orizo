<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Bootstrap/App.php';

use Orixo\Bootstrap\App;
use Orixo\Bootstrap\Router;
use Orixo\Controllers\AdminController;
use Orixo\Middleware\AuthMiddleware;
use Orixo\Middleware\RoleMiddleware;

App::bootHttp();

$adminGuard = [
    AuthMiddleware::class,
    [RoleMiddleware::class, 'handle', [['admin']]],
];
$teamOrAdminGuard = [
    AuthMiddleware::class,
    [RoleMiddleware::class, 'handle', [['admin', 'team_member']]],
];

$router = new Router();

// Admin-only routes
$router->get('/admin', [AdminController::class, 'dashboard'], $adminGuard);
$router->get('/admin/clients', [AdminController::class, 'clients'], $adminGuard);
$router->get('/admin/inquiries', [AdminController::class, 'inquiries'], $adminGuard);
$router->get('/admin/inquiries/{id}', [AdminController::class, 'inquiries'], $adminGuard);
$router->get('/admin/projects', [AdminController::class, 'projects'], $adminGuard);
$router->get('/admin/projects/{id}', [AdminController::class, 'projectDetail'], $adminGuard);
$router->get('/admin/team', [AdminController::class, 'team'], $adminGuard);
$router->get('/admin/invoices', [AdminController::class, 'invoices'], $adminGuard);
$router->get('/admin/invoices/{id}', [AdminController::class, 'invoiceDetail'], $adminGuard);
$router->get('/admin/cms', [AdminController::class, 'cms'], $adminGuard);
$router->get('/admin/users', [AdminController::class, 'users'], $adminGuard);
$router->get('/admin/settings', [AdminController::class, 'settings'], $adminGuard);
$router->get('/admin/email-queue', [AdminController::class, 'emailQueue'], $adminGuard);
$router->get('/admin/activity-logs', [AdminController::class, 'activityLogs'], $adminGuard);

// Restricted Team Workspace routes (team_member or admin)
$router->get('/admin/workspace', [AdminController::class, 'workspaceDashboard'], $teamOrAdminGuard);
$router->get('/admin/workspace/projects', [AdminController::class, 'workspaceProjects'], $teamOrAdminGuard);
$router->get('/admin/workspace/projects/{id}', [AdminController::class, 'workspaceProjectDetail'], $teamOrAdminGuard);
$router->get('/admin/workspace/profile', [AdminController::class, 'workspaceProfile'], $teamOrAdminGuard);

$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH) ?: '/admin';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $reqPath, false);
