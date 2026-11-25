<?php
declare(strict_types=1);

// Support PHP built-in server static assets & boundary forwarding
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (PHP_SAPI === 'cli-server') {
    $staticFile = __DIR__ . $reqPath;
    if ($reqPath !== '/' && is_file($staticFile)) {
        return false;
    }
}

if (str_starts_with($reqPath, '/api/')) {
    require __DIR__ . '/api.php';
    return;
}
if ($reqPath === '/portal' || str_starts_with($reqPath, '/portal/')) {
    require __DIR__ . '/portal.php';
    return;
}
if ($reqPath === '/admin' || str_starts_with($reqPath, '/admin/')) {
    require __DIR__ . '/admin.php';
    return;
}

require_once dirname(__DIR__) . '/app/Bootstrap/App.php';

use Orixo\Bootstrap\App;
use Orixo\Bootstrap\Router;
use Orixo\Controllers\PublicController;
use Orixo\Middleware\CsrfMiddleware;

App::bootHttp();

$router = new Router();
$router->get('/', [PublicController::class, 'home']);
$router->get('/about', [PublicController::class, 'about']);
$router->get('/services', [PublicController::class, 'services']);
$router->get('/services/{slug}', [PublicController::class, 'serviceDetail']);
$router->get('/portfolio', [PublicController::class, 'portfolio']);
$router->get('/portfolio/{slug}', [PublicController::class, 'portfolioDetail']);
$router->get('/case-studies', [PublicController::class, 'caseStudies']);
$router->get('/case-studies/{slug}', [PublicController::class, 'caseStudyDetail']);
$router->get('/industries', [PublicController::class, 'industries']);
$router->get('/industries/{slug}', [PublicController::class, 'industryDetail']);
$router->get('/our-process', [PublicController::class, 'ourProcess']);
$router->get('/technologies', [PublicController::class, 'technologies']);
$router->get('/team', [PublicController::class, 'team']);
$router->get('/contact', [PublicController::class, 'contact']);
$router->get('/project-inquiry', [PublicController::class, 'projectInquiry']);
$router->get('/login', [PublicController::class, 'login']);
$router->post('/login', [PublicController::class, 'handleLogin'], [CsrfMiddleware::class]);
$router->get('/activate-account', [PublicController::class, 'activateAccount']);
$router->get('/forgot-password', [PublicController::class, 'forgotPassword']);
$router->get('/reset-password', [PublicController::class, 'resetPassword']);
$router->get('/privacy-policy', static fn () => (new PublicController())->legalPage('privacy_policy', 'Privacy Policy'));
$router->get('/terms-and-conditions', static fn () => (new PublicController())->legalPage('terms_conditions', 'Terms and Conditions'));
$router->get('/403', [PublicController::class, 'forbidden']);
$router->get('/404', [PublicController::class, 'notFound']);
$router->get('/maintenance', [PublicController::class, 'maintenance']);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $reqPath, false);
