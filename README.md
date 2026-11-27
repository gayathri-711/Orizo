# Orixo — Creative Digital Solutions & Innovation Studio

Full-stack digital agency website, **Client Portal**, **Restricted Team Workspace**, and **Admin Operations Platform** built with **Native PHP 8.3**, **MySQL 8.0+**, **Bootstrap 5**, **Dompdf**, and **Docker**.

**Live Production URL (Railway):** https://orizo-production.up.railway.app

---

## Tech Stack

| Layer | Technologies |
|---|---|
| **Frontend** | HTML5, CSS3 (design tokens + custom styling), JavaScript (ES6+), jQuery `3.7.1`, Bootstrap `5.3.3`, Font Awesome `6.5.2`, Google Fonts (`Plus Jakarta Sans`, `Inter`, `JetBrains Mono`) |
| **Backend** | Native PHP `>= 8.2` (running **PHP 8.3** on **Apache** with `mod_rewrite` & `mod_headers`, custom PSR-4 `Orixo\` router/MVC architecture) |
| **Database** | **MySQL 8.0+** (`InnoDB`, `utf8mb4_unicode_ci`) with **PHP PDO** prepared statements (`ATTR_EMULATE_PREPARES = false`, ACID transactions, optional SSL CA support) |
| **Packages (`composer.json`)** | `dompdf/dompdf` (`^3.0`) for PDF invoices, `phpmailer/phpmailer` (`^6.9`) for SMTP email fallback, `vlucas/phpdotenv` (`^5.6`) for environment config |
| **Email Transport** | Queue-based `EmailQueueService` supporting **Google Apps Script webhook** (`apps_script`) and **PHPMailer SMTP** (`smtp`) |
| **Infrastructure** | **Docker** (`php:8.3-apache`), **Docker Compose** (local MySQL + Apache + Cloudflare Tunnel), **Railway** (cloud hosting + persistent volume + MySQL) |

---

## Key Features

### 1. Public Agency Website & CMS
- **Public Pages:** Home, About, Services & Service Details, Portfolio & Details, Case Studies & Details, Industries & Details, Our Process, Technologies, Team, Privacy Policy, and Terms & Conditions (`public/index.php`).
- **Admin CMS (`/admin/cms`):** Full management for service categories, services, portfolio projects, case studies, industries, technologies, testimonials, and editable CMS pages.

### 2. Project Inquiry & Lead Conversion Pipeline
- Public **Contact** (`/contact`) and **Project Inquiry** (`/project-inquiry`) forms with file attachments and auto-generated `INQ` reference codes.
- Admin inquiry triage (`/admin/inquiries`) with one-click **Convert to Client & Project**, creating the user/client records and queuing an activation email.

### 3. Security & Role-Based Access Control (RBAC)
- **Invitation-Only Access:** Open self-registration is disabled; clients and team members activate accounts via expiring tokens (`/activate-account`) and reset passwords via `/forgot-password` and `/reset-password`.
- **3 Isolated Roles:** `admin`, `team_member`, and `client`, enforced by `AuthMiddleware` and `RoleMiddleware` across both web routes and `/api/v1/...` endpoints (`public/api.php`).
- **Hardening:** Synchronizer CSRF tokens (`CsrfMiddleware`), `HttpOnly` / `SameSite=Lax` cookies with idle & absolute session timeouts, login attempt logging & brute-force lockout, and `token_version` session invalidation.

### 4. Client Portal (`/portal`)
- Track project status, milestones, and deliverables; browse shared documents and upload client files; collaborate in project message threads; view notifications; manage profile/password; and view or download **Dompdf-generated INR (`₹`) Invoice PDFs**.

### 5. Restricted Team Workspace (`/admin/workspace`)
- Scoped strictly to assigned projects: create and update tasks (with `task_status_history` audit trail), upload and review documents, submit or replace deliverables, and message inside assigned project threads without exposing billing or organization-wide admin data.

### 6. Admin Operations, Invoicing & Audit (`/admin`)
- Manage clients, team members, users, projects, team assignments, milestones, and deliverable publishing/rejection.
- **Billing & Payment Ledger:** Create, edit, issue, and cancel invoices; record or void payments (`bank_transfer`, `upi`, `neft_rtgs`, `wire`, `check`, `cash`, `card_offline`, `other`); and generate PDF invoices.
- **System Controls:** Editable email templates (`email_templates`), retryable email queue (`email_queue_logs`), platform settings (`app_settings`), and activity audit logs (`activity_logs`).
- **Protected Storage:** Inquiry attachments, deliverables, internal files, and invoice PDFs live outside webroot in `storage/private/` with MIME validation, SHA-256 checksums (`file_metadata`), and role-checked download endpoints.

---

## Project Structure

```text
Orizo/
├── app/                # Bootstrap, Controllers (Web & API), Helpers, Middleware, Models, Services, Views
├── bin/                # CLI database migration & seed runner (migrate.php) and queue worker
├── config/             # App, database, mail, and file upload configuration
├── database/           # SQL migrations (001–003) and idempotent SeedRunner
├── docker/             # Container entrypoint script (dynamic PORT, Apache MPM guard, DB migrate/seed)
├── docs/               # Setup docs & Google Apps Script mail webhook helper
├── public/             # Webroot (index.php, portal.php, admin.php, api.php, assets/, uploads/)
├── storage/            # Private uploaded files, generated PDFs, and application logs
├── tests/              # Automated security/smoke suite and live HTTP + RBAC E2E check
├── composer.json
├── Dockerfile
├── docker-compose.yml
└── railway.toml
```

---

## Local Setup (Docker)

1. Copy `.env.example` to `.env` and update any values as needed:
   ```bash
   cp .env.example .env
   ```
2. Build and start the containers (`db`, `app`, and `tunnel`):
   ```bash
   docker compose up -d --build
   ```
   The container entrypoint (`docker/entrypoint.sh`) waits for MySQL, runs `php bin/migrate.php --seed`, and starts Apache on port `8000`.
3. Open **http://localhost:8000** in your browser.
   - Local MySQL is exposed at `127.0.0.1:3307` (`orixo_db` / `orixo`).
4. Run the automated verification suites inside the container:
   ```bash
   docker exec orixo_app php tests/smoke_and_security_tests.php
   docker exec orixo_app php tests/e2e_http_check.php
   ```

---

## Demo Login Accounts

Seeded automatically by `database/seeds/SeedRunner.php`:

| Role | Email | Password | Portal |
|---|---|---|---|
| **Admin** | `admin@orixo.studio` | `OrixoAdmin@2026!` | `/admin` |
| **Team Member** | `arjun@orixo.studio` | `OrixoTeam@2026!` | `/admin/workspace` |
| **Client** | `rohan@nexafin.in` | `OrixoClient@2026!` | `/portal` |

*(Additional seeded team accounts: `meera@orixo.studio` and `kabir@orixo.studio` with `OrixoTeam@2026!`.)*

---

## Environment Variables

Configure via `.env` locally or service variables in production (`.env.example`):

- **App & Sessions:** `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_TIMEZONE`, `SESSION_NAME`, `SESSION_IDLE_TIMEOUT`, `SESSION_ABSOLUTE_LIFETIME`, `SESSION_COOKIE_SECURE`
- **Database:** `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SSL_CA` (`system` for system CA bundle when cloud MySQL requires TLS), `DB_SSL_VERIFY`
- **Mail:** `MAIL_DRIVER` (`apps_script` or `smtp`), `APPS_SCRIPT_WEBHOOK_URL`, `APPS_SCRIPT_SECRET`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `ADMIN_ALERT_EMAIL`

---

## Railway Deployment

- Uses **`Dockerfile`** (`railway.toml`) with dynamic **`PORT`** support in `docker/entrypoint.sh` and persistent volume mounted at **`/var/www/html/storage`**.
- Set the Railway domain's **Target Port** in Networking to match **`PORT`** (for example, `PORT=8080` with target port `8080`) and set **`APP_URL`** to your full `https://...` domain.
- To deploy local changes directly via Railway CLI:
  ```bash
  railway up
  ```
- To inspect the private Railway MySQL database from **MySQL Workbench** over an encrypted SSH tunnel (port `3308` avoids colliding with local Docker port `3307`):
  ```bash
  railway connect MySQL --tunnel-only -P 3308
  ```
