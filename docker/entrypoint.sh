#!/usr/bin/env bash
set -e

cd /var/www/html

if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
  echo "[Orixo] Installing Composer dependencies..."
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

echo "[Orixo] Waiting for MySQL..."
until php -r 'try { new PDO("mysql:host=" . (getenv("DB_HOST") ?: "db") . ";port=" . (getenv("DB_PORT") ?: "3306") . ";dbname=" . (getenv("DB_DATABASE") ?: "orixo_db"), getenv("DB_USERNAME") ?: "orixo", getenv("DB_PASSWORD") ?: "orixo_dev_password"); exit(0); } catch (Throwable $e) { exit(1); }' 2>/dev/null; do
  sleep 2
done

echo "[Orixo] Running database migrations and seed..."
php bin/migrate.php --seed

chown -R www-data:www-data storage public/uploads

exec "$@"
