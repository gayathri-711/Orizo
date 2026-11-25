#!/usr/bin/env bash
set -e

cd /var/www/html

# Support Railway dynamic PORT (defaults to 80 locally)
APP_PORT="${PORT:-80}"
sed -ri "s/^Listen [0-9]+/Listen ${APP_PORT}/g" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${APP_PORT}>/g" /etc/apache2/sites-available/*.conf

if [ ! -f .env ] && [ -z "${APP_ENV:-}" ] && [ -f .env.example ]; then
  cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
  echo "[Orixo] Installing Composer dependencies..."
  composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
fi

# Ensure private storage subdirectories exist (important when mounting Railway Volume at /var/www/html/storage)
mkdir -p storage/private/inquiries storage/private/projects storage/private/deliverables storage/private/messages storage/private/invoices storage/logs public/uploads/cms
chown -R www-data:www-data storage public/uploads

DB_WAIT_HOST="${DB_HOST:-${MYSQLHOST:-db}}"
DB_WAIT_PORT="${DB_PORT:-${MYSQLPORT:-3306}}"
DB_WAIT_NAME="${DB_DATABASE:-${MYSQLDATABASE:-orixo_db}}"
DB_WAIT_USER="${DB_USERNAME:-${MYSQLUSER:-orixo}}"
DB_WAIT_PASS="${DB_PASSWORD:-${MYSQLPASSWORD:-orixo_dev_password}}"

echo "[Orixo] Waiting for MySQL (${DB_WAIT_HOST}:${DB_WAIT_PORT}/${DB_WAIT_NAME})..."
until php -r '
try {
    new PDO(
        "mysql:host=" . $argv[1] . ";port=" . $argv[2] . ";dbname=" . $argv[3],
        $argv[4],
        $argv[5]
    );
    exit(0);
} catch (Throwable $e) {
    exit(1);
}' "$DB_WAIT_HOST" "$DB_WAIT_PORT" "$DB_WAIT_NAME" "$DB_WAIT_USER" "$DB_WAIT_PASS" 2>/dev/null; do
  sleep 2
done

echo "[Orixo] Running database migrations and seed..."
php bin/migrate.php --seed

chown -R www-data:www-data storage public/uploads

exec "$@"
