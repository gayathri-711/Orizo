# Orixo — Google Apps Script Email Setup Guide

Orixo supports sending all 14 transactional email templates through a **Google Apps Script Web App** (`MAIL_DRIVER=apps_script`), backed by the persistent MySQL `email_queue_logs` retry queue.

## 1. Deploy the Google Apps Script Web App
1. Open [Google Apps Script](https://script.google.com) while signed into the Google account that should send Orixo emails.
2. Click **New project** and replace the contents of `Code.gs` with [Code.gs](file:///c:/Users/janan/Downloads/Orizo/docs/google_apps_script/Code.gs).
3. Go to **Project Settings** (gear icon) → **Script Properties** → **Add script property**:
   - **Property:** `ORIXO_MAIL_SECRET`
   - **Value:** Choose a strong secret string (for example `orixo_gas_secret_9841_change_me`).
4. Click **Deploy** → **New deployment**:
   - **Select type:** `Web app`
   - **Execute as:** `Me`
   - **Who has access:** `Anyone` *(access is still strictly authenticated by `ORIXO_MAIL_SECRET`)*
5. Authorize the script when Google prompts for `MailApp` permission and copy the **Web app URL** (`https://script.google.com/macros/s/.../exec`).

## 2. Configure Orixo `.env`
Open `.env` in the project root and set:
```env
MAIL_DRIVER=apps_script
APPS_SCRIPT_WEBHOOK_URL=https://script.google.com/macros/s/YOUR_DEPLOYMENT_ID/exec
APPS_SCRIPT_SECRET=orixo_gas_secret_9841_change_me
```
*(Never place `APPS_SCRIPT_WEBHOOK_URL` or `APPS_SCRIPT_SECRET` in frontend JavaScript — PHP reads them strictly server-side.)*

## 3. Test Delivery
1. Submit a test inquiry at http://localhost:8000/project-inquiry (or issue an invoice in `/admin/invoices`).
2. Check http://localhost:8000/admin/email-queue to confirm the job status shows **`sent`** (or click **Run Queue Now** to process any previously queued jobs).
