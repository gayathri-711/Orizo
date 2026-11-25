<?php
declare(strict_types=1);

return [
    'driver' => strtolower((string) ($_ENV['MAIL_DRIVER'] ?? 'apps_script')),
    'apps_script' => [
        'webhook_url' => trim((string) ($_ENV['APPS_SCRIPT_WEBHOOK_URL'] ?? '')),
        'secret' => trim((string) ($_ENV['APPS_SCRIPT_SECRET'] ?? '')),
        'timeout' => 15,
    ],
    'host' => $_ENV['SMTP_HOST'] ?? 'smtp.example.com',
    'port' => (int) ($_ENV['SMTP_PORT'] ?? 587),
    'encryption' => $_ENV['SMTP_ENCRYPTION'] ?? 'tls',
    'username' => $_ENV['SMTP_USERNAME'] ?? '',
    'password' => $_ENV['SMTP_PASSWORD'] ?? '',
    'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@orixo.studio',
    'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'Orixo Studio',
    'admin_alert_email' => $_ENV['ADMIN_ALERT_EMAIL'] ?? 'hello@orixo.studio',
    'max_attempts' => 3,
    'retry_delays_minutes' => [1 => 5, 2 => 15],
    'processing_lease_seconds' => 120,
];
