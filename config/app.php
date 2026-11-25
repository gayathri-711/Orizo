<?php
declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'Orixo',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
    'url' => rtrim($_ENV['APP_URL'] ?? 'http://localhost:8000', '/'),
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Asia/Kolkata',
    'session' => [
        'name' => $_ENV['SESSION_NAME'] ?? 'ORIXO_SESSID',
        'idle_timeout' => (int) ($_ENV['SESSION_IDLE_TIMEOUT'] ?? 1800),
        'absolute_lifetime' => (int) ($_ENV['SESSION_ABSOLUTE_LIFETIME'] ?? 28800),
        'cookie_secure' => filter_var($_ENV['SESSION_COOKIE_SECURE'] ?? false, FILTER_VALIDATE_BOOL),
    ],
];
