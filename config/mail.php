<?php
declare(strict_types=1);

return [
    'driver' => strtolower((string) env_val('MAIL_DRIVER', 'apps_script')),
    'apps_script' => [
        'webhook_url' => trim((string) env_val('APPS_SCRIPT_WEBHOOK_URL', '')),
        'secret' => trim((string) env_val('APPS_SCRIPT_SECRET', '')),
        'timeout' => 15,
    ],
    'host' => env_val('SMTP_HOST', 'smtp.example.com'),
    'port' => (int) env_val('SMTP_PORT', 587),
    'encryption' => env_val('SMTP_ENCRYPTION', 'tls'),
    'username' => env_val('SMTP_USERNAME', ''),
    'password' => env_val('SMTP_PASSWORD', ''),
    'from_address' => env_val('MAIL_FROM_ADDRESS', 'noreply@orixo.studio'),
    'from_name' => env_val('MAIL_FROM_NAME', 'Orixo Studio'),
    'admin_alert_email' => env_val('ADMIN_ALERT_EMAIL', 'hello@orixo.studio'),
    'max_attempts' => 3,
    'retry_delays_minutes' => [1 => 5, 2 => 15],
    'processing_lease_seconds' => 120,
];
