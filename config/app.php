<?php
declare(strict_types=1);

return [
    'name' => env_val('APP_NAME', 'Orixo'),
    'env' => env_val('APP_ENV', 'production'),
    'debug' => filter_var(env_val('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
    'url' => rtrim((string) env_val('APP_URL', 'http://localhost:8000'), '/'),
    'timezone' => env_val('APP_TIMEZONE', 'Asia/Kolkata'),
    'session' => [
        'name' => env_val('SESSION_NAME', 'ORIXO_SESSID'),
        'idle_timeout' => (int) env_val('SESSION_IDLE_TIMEOUT', 1800),
        'absolute_lifetime' => (int) env_val('SESSION_ABSOLUTE_LIFETIME', 28800),
        'cookie_secure' => filter_var(env_val('SESSION_COOKIE_SECURE', false), FILTER_VALIDATE_BOOL),
    ],
];
