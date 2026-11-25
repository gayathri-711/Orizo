<?php
declare(strict_types=1);

namespace Orixo\Bootstrap;

/**
 * Application bootstrapper for HTTP and CLI execution.
 */
final class App
{
    private static bool $booted = false;
    private static array $config = [];

    public static function rootPath(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function bootHttp(): void
    {
        self::bootCommon();
        self::sendSecurityHeaders();
        self::startSecureSession();
    }

    public static function bootCli(): void
    {
        self::bootCommon();
    }

    private static function bootCommon(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        $root = self::rootPath();
        $vendorAutoload = $root . '/vendor/autoload.php';
        if (file_exists($vendorAutoload)) {
            require_once $vendorAutoload;
        } else {
            // Minimal PSR-4 fallback so classes resolve cleanly before composer install
            spl_autoload_register(static function (string $class) use ($root): void {
                if (str_starts_with($class, 'Orixo\\')) {
                    $rel = str_replace('\\', '/', substr($class, 6));
                    $file = $root . '/app/' . $rel . '.php';
                    if (is_file($file)) {
                        require_once $file;
                    }
                }
            });
            require_once $root . '/app/Helpers/functions.php';
        }

        self::loadEnv($root);

        self::$config = [
            'app' => require $root . '/config/app.php',
            'database' => require $root . '/config/database.php',
            'mail' => require $root . '/config/mail.php',
            'uploads' => require $root . '/config/uploads.php',
        ];

        date_default_timezone_set(self::$config['app']['timezone'] ?? 'Asia/Kolkata');

        if (!(self::$config['app']['debug'] ?? false)) {
            ini_set('display_errors', '0');
        }

        set_exception_handler(static function (\Throwable $e): void {
            self::logError('Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, "[ERROR] " . $e->getMessage() . PHP_EOL);
                exit(1);
            }
            $isApi = str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');
            http_response_code(500);
            if ($isApi) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'message' => (self::$config['app']['debug'] ?? false) ? $e->getMessage() : 'An internal server error occurred.',
                    'data' => null,
                    'meta' => [],
                ]);
            } else {
                echo '<!doctype html><html><body style="font-family:sans-serif;padding:40px;background:#F7F5F0;color:#18181B;"><h2>500 — Unexpected Error</h2><p>Something went wrong on our end. Please try again shortly.</p></body></html>';
            }
            exit;
        });
    }

    public static function config(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $cursor = self::$config;
        foreach ($parts as $p) {
            if (!is_array($cursor) || !array_key_exists($p, $cursor)) {
                return $default;
            }
            $cursor = $cursor[$p];
        }
        return $cursor;
    }

    private static function loadEnv(string $root): void
    {
        if (!is_file($root . '/.env')) {
            return;
        }
        if (class_exists(\Dotenv\Dotenv::class)) {
            \Dotenv\Dotenv::createImmutable($root)->safeLoad();
            return;
        }
        $lines = file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $trimmed, 2);
            $k = trim($k);
            $v = trim($v, " \t\n\r\0\x0B\"'");
            if (!array_key_exists($k, $_ENV)) {
                $_ENV[$k] = $v;
                $_SERVER[$k] = $v;
            }
        }
    }

    private static function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
    }

    private static function startSecureSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $sessCfg = self::$config['app']['session'];
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443)
            || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
            || ($sessCfg['cookie_secure'] ?? false);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name($sessCfg['name'] ?? 'ORIXO_SESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $now = time();
        $idleTimeout = (int) ($sessCfg['idle_timeout'] ?? 1800);
        $absLifetime = (int) ($sessCfg['absolute_lifetime'] ?? 28800);

        if (isset($_SESSION['user_id'])) {
            $createdAt = (int) ($_SESSION['_created_at'] ?? $now);
            $lastActivity = (int) ($_SESSION['_last_activity'] ?? $now);
            if (($now - $lastActivity) > $idleTimeout || ($now - $createdAt) > $absLifetime) {
                self::destroySession();
                session_start();
            } else {
                $_SESSION['_last_activity'] = $now;
            }
        }

        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public static function destroySession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }
        session_destroy();
    }

    public static function logError(string $message, string $channel = 'app'): void
    {
        $logDir = self::rootPath() . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0750, true);
        }
        $line = sprintf("[%s] %s%s", date('Y-m-d H:i:s'), $message, PHP_EOL);
        @file_put_contents($logDir . '/' . preg_replace('/[^a-z0-9_-]/i', '', $channel) . '.log', $line, FILE_APPEND | LOCK_EX);
    }
}
