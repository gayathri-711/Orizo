<?php
declare(strict_types=1);

use Orixo\Bootstrap\App;
use Orixo\Bootstrap\Database;

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    return (string) ($_SESSION['_csrf_token'] ?? '');
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . e(csrf_token()) . '">';
}

function url(string $path = '/'): string
{
    $base = App::config('app.url', '');
    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '/' : $path);
}

function asset(string $path): string
{
    $rel = '/assets/' . ltrim($path, '/');
    $full = App::rootPath() . '/public' . $rel;
    $ver = is_file($full) ? (string) filemtime($full) : '';
    return $ver !== '' ? ($rel . '?v=' . $ver) : $rel;
}

function json_response(bool $success, string $message, mixed $data = null, array $meta = [], int $statusCode = 200, array $errors = []): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    $payload = [
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'meta' => $meta,
    ];
    if (!empty($errors)) {
        $payload['errors'] = $errors;
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== false && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'item-' . bin2hex(random_bytes(3));
}

/**
 * Format monetary amount using each invoice's own ISO currency_code.
 * Applies Indian numbering system (e.g. ₹1,50,000.00) for INR.
 */
function format_currency(float|int|string $amount, string $currencyCode = 'INR'): string
{
    $num = (float) $amount;
    $negative = $num < 0;
    $abs = abs($num);
    $code = strtoupper(trim($currencyCode ?: 'INR'));

    $symbols = [
        'INR' => '₹',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'AED' => 'AED ',
        'SGD' => 'S$',
    ];
    $symbol = $symbols[$code] ?? ($code . ' ');

    if ($code === 'INR') {
        $fixed = number_format($abs, 2, '.', '');
        [$intPart, $decPart] = explode('.', $fixed);
        if (strlen($intPart) > 3) {
            $lastThree = substr($intPart, -3);
            $rest = substr($intPart, 0, -3);
            $restFormatted = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $formatted = $restFormatted . ',' . $lastThree . '.' . $decPart;
        } else {
            $formatted = $intPart . '.' . $decPart;
        }
    } else {
        $formatted = number_format($abs, 2, '.', ',');
    }

    return ($negative ? '-' : '') . $symbol . $formatted;
}

function format_date(?string $date, string $format = 'd M Y'): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

function current_user(): ?array
{
    return $GLOBALS['_orixo_auth_user'] ?? null;
}

function set_current_user(?array $user): void
{
    $GLOBALS['_orixo_auth_user'] = $user;
}

function app_settings_all(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $rows = Database::connection()->query("SELECT setting_key, setting_value FROM app_settings")->fetchAll();
        $cache = [];
        foreach ($rows as $r) {
            $cache[$r['setting_key']] = $r['setting_value'];
        }
        return $cache;
    } catch (\Throwable) {
        return [];
    }
}

function setting(string $key, string $default = ''): string
{
    $all = app_settings_all();
    return (string) ($all[$key] ?? $default);
}

function render_view(string $viewPath, array $data = [], ?string $layout = null): void
{
    $viewsRoot = App::rootPath() . '/app/Views/';
    $viewFile = $viewsRoot . ltrim($viewPath, '/') . '.php';
    if (!is_file($viewFile)) {
        throw new \RuntimeException("View not found: {$viewPath}");
    }

    $data['settings'] = $data['settings'] ?? app_settings_all();
    $data['authUser'] = $data['authUser'] ?? current_user();
    extract($data, EXTR_SKIP);

    ob_start();
    require $viewFile;
    $content = (string) ob_get_clean();

    if ($layout !== null) {
        $layoutFile = $viewsRoot . ltrim($layout, '/') . '.php';
        if (!is_file($layoutFile)) {
            throw new \RuntimeException("Layout not found: {$layout}");
        }
        require $layoutFile;
    } else {
        echo $content;
    }
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function pagination_params(int $defaultPerPage = 15): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(100, max(5, (int) ($_GET['per_page'] ?? $defaultPerPage)));
    $offset = ($page - 1) * $perPage;
    return [$page, $perPage, $offset];
}
