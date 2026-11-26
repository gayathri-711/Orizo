<?php
declare(strict_types=1);

return [
    'host' => env_val('DB_HOST', env_val('MYSQLHOST', '127.0.0.1')),
    'port' => (int) env_val('DB_PORT', env_val('MYSQLPORT', 3306)),
    'database' => env_val('DB_DATABASE', env_val('MYSQLDATABASE', 'orixo_db')),
    'username' => env_val('DB_USERNAME', env_val('MYSQLUSER', 'root')),
    'password' => env_val('DB_PASSWORD', env_val('MYSQLPASSWORD', '')),
    'ssl_ca' => env_val('DB_SSL_CA', ''),
    'ssl_verify' => filter_var(env_val('DB_SSL_VERIFY', true), FILTER_VALIDATE_BOOL),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];
