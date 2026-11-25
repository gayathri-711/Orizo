<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Bootstrap/App.php';

use Orixo\Bootstrap\App;
use Orixo\Bootstrap\Router;
use Orixo\Controllers\PortalController;
use Orixo\Middleware\AuthMiddleware;
use Orixo\Middleware\RoleMiddleware;

App::bootHttp();

$guard = [
    AuthMiddleware::class,
    [RoleMiddleware::class, 'handle', [['client']]],
];

$router = new Router();
$router->get('/portal', [PortalController::class, 'dashboard'], $guard);
$router->get('/portal/projects', [PortalController::class, 'projects'], $guard);
$router->get('/portal/projects/{id}', [PortalController::class, 'projectDetail'], $guard);
$router->get('/portal/deliverables', [PortalController::class, 'deliverables'], $guard);
$router->get('/portal/documents', [PortalController::class, 'documents'], $guard);
$router->get('/portal/messages', [PortalController::class, 'messages'], $guard);
$router->get('/portal/invoices', [PortalController::class, 'invoices'], $guard);
$router->get('/portal/invoices/{id}', [PortalController::class, 'invoiceDetail'], $guard);
$router->get('/portal/notifications', [PortalController::class, 'notifications'], $guard);
$router->get('/portal/profile', [PortalController::class, 'profile'], $guard);

$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/portal', PHP_URL_PATH) ?: '/portal';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $reqPath, false);
