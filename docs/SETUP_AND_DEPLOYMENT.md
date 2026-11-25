# Orixo — Setup, Database Installation & Deployment Guide

## 1. Prerequisites
- **PHP:** 8.2+ with `pdo_mysql`, `fileinfo`, `mbstring`, `openssl`, `json`, `dom` extensions enabled
- **MySQL:** 8.0+ (or MySQL 9.x / MariaDB 10.6+)
- **Composer:** Latest v2.x
- **Web Server:** Apache (with `mod_rewrite`) or Nginx pointing strictly to `/public`

---

## 2. Installation Steps

1. **Install Composer Dependencies**
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
   Installs `phpmailer/phpmailer`, `dompdf/dompdf`, and `vlucas/phpdotenv`.

2. **Configure Environment Secrets**
   ```bash
   cp .env.example .env
   ```
   Update `.env` with your MySQL credentials, `APP_URL`, and authenticated SMTP settings (`SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`). Ensure `.env` is readable only by the server user (`chmod 600 .env`) and never placed inside `/public`.

3. **Create MySQL Database**
   ```sql
   CREATE DATABASE orixo_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

4. **Run Database Migrations & Seed Data**
   ```bash
   php bin/migrate.php --seed
   ```
   Applies ordered migrations `001` through `003` (including the circular FK sequence between `clients` and `inquiries`) and seeds roles, default settings (`INR`), 14 email templates, CMS catalog, and demo records.

5. **Set Writable Permissions**
   Ensure the web server user can write to:
   - `storage/private/`
   - `storage/logs/`
   - `public/uploads/cms/`

---

## 3. Demo Credentials (Seeded for Testing)

| Role | Email | Password | Portal Entry |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin@orixo.studio` | `OrixoAdmin@2026!` | `/admin` |
| **Team Member (Lead)** | `arjun@orixo.studio` | `OrixoTeam@2026!` | `/admin/workspace` |
| **Client** | `rohan@nexafin.in` | `OrixoClient@2026!` | `/portal` |

> **Security Reminder:** Change or deactivate all demo accounts before going live in production.

---

## 4. Scheduled Cron Jobs (Production)

Add these two cron entries to run the persistent email retry queue and reference-safe orphan file cleanup:

```cron
# Process pending & failed SMTP emails every 2 minutes
*/2 * * * * /usr/bin/php /var/www/orixo/bin/process_email_queue.php >> /var/www/orixo/storage/logs/cron_mail.log 2>&1

# Clean up unreferenced private files older than 24 hours daily at 03:00
0 3 * * * /usr/bin/php /var/www/orixo/bin/cleanup_orphan_files.php >> /var/www/orixo/storage/logs/cron_cleanup.log 2>&1
```

---

## 5. Web Server Configuration

- **DocumentRoot** must point strictly to `/path/to/orixo/public` (never the project root).
- Enable HTTPS/TLS and set `SESSION_COOKIE_SECURE=true` and `APP_DEBUG=false` in `.env` for production.
- For local testing with PHP's built-in server:
  ```bash
  php -S localhost:8000 -t public public/index.php
  ```

---

## 6. Verification Suite

After installing Composer dependencies and running migrations/seeds:
```bash
php tests/smoke_and_security_tests.php
```
