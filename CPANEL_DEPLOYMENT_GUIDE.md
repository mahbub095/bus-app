# SonyaBus — cPanel Deployment Guide

Step-by-step instructions to deploy **SonyaBus** on shared hosting with **cPanel**.

SonyaBus has two parts:

| Part | Technology | Purpose |
|------|------------|---------|
| **Customer app** | React (Vite) | Public booking site |
| **Backend** | Laravel 12 | REST API (`/api/*`), admin panel (`/admin/*`), payments |

On cPanel, deploy them on **two domains/subdomains** pointing to the same hosting account.

---

## 1. Recommended domain layout

Example (replace with your real domain):

| URL | Document root | Serves |
|-----|---------------|--------|
| `https://yourdomain.com` | `frontend/dist` contents | Customer React app |
| `https://api.yourdomain.com` | `backend/public` | Laravel API + admin |

Why two URLs?

- The React app calls the API over HTTP (`/api/*`).
- The admin dashboard lives at `https://api.yourdomain.com/admin/login`.
- Payment callbacks use `https://api.yourdomain.com/payment/callback`.

You can name the backend subdomain `api`, `app`, or `panel` — just use the same URL everywhere below.

---

## 2. Server requirements

Confirm with your host (cPanel → **Select PHP Version** or **MultiPHP Manager**):

| Requirement | Minimum |
|-------------|---------|
| PHP | **8.2+** |
| MySQL | **8.0+** (or MariaDB 10.6+) |
| Composer | 2.x (via SSH, or upload `vendor/` from your PC) |
| Node.js | 20+ (build React **on your computer**, then upload `dist/`) |
| PHP extensions | `openssl`, `pdo`, `pdo_mysql`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `curl` |

Optional but recommended:

- **SSH / Terminal** in cPanel (makes `composer` and `artisan` much easier)
- **Cron Jobs** (required if SMS queue is used)

---

## 3. Prepare files on your computer

Do this **before** uploading to cPanel.

### 3.1 Build the React frontend

Create `frontend/.env.production` (or export vars before build):

```env
VITE_API_BASE_URL=https://api.yourdomain.com/api
VITE_ZINIPAY_API_KEY=your_zinipay_key_here
```

Then build:

```bash
cd frontend
npm install
npm run build
```

Output folder: `frontend/dist/` — you will upload **everything inside `dist/`**, not the `dist` folder name itself (see step 6).

> **Important:** The source currently hardcodes `http://localhost:8000/api` in `frontend/src/App.jsx`. Before building for production, change it to use the env variable:
>
> ```js
> const API_BASE = import.meta.env.VITE_API_BASE_URL ?? '/api';
> ```
>
> Also replace hardcoded `http://localhost:8000` for favicon URLs with your live API domain or a relative path.

Build again after that change.

### 3.2 Install Laravel dependencies

```bash
cd backend
composer install --no-dev --optimize-autoloader
```

This creates the `vendor/` folder. If your host has **no SSH and no Composer**, you must upload this `vendor/` folder from your PC.

### 3.3 Create the production `.env`

On your PC:

```bash
cd backend
cp .env.example .env
```

Edit `.env` for production (full example in section 5). Do **not** upload `.env` to a public folder — it stays in `backend/`, one level above `public/`.

---

## 4. Create MySQL database in cPanel

1. Log in to **cPanel**.
2. Open **MySQL® Databases**.
3. Create a database, e.g. `youruser_sonyabus`.
4. Create a database user with a strong password.
5. **Add User To Database** → grant **ALL PRIVILEGES**.
6. Note these values for `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=youruser_sonyabus
DB_USERNAME=youruser_sonyabus_user
DB_PASSWORD=your_strong_password
```

> On many cPanel hosts, `DB_HOST` is `localhost`. If connection fails, check your host’s docs (sometimes `127.0.0.1`).

---

## 5. Upload project files

### 5.1 Folder layout on the server

Use **File Manager** or **FTP** (FileZilla). Example layout under your account home (`/home/youruser/`):

```text
/home/youruser/
├── backend/                 ← Laravel app (NOT web-accessible as a whole)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── public/              ← document root for api.yourdomain.com
│   ├── routes/
│   ├── storage/
│   ├── vendor/
│   ├── .env
│   └── ...
└── frontend-public/         ← optional folder name; contents = React dist
    ├── index.html
    ├── assets/
    └── ...
```

**Do not upload:**

- `node_modules/`
- `.git/`
- Local-only `.env` files with dev secrets
- `backend/tests/` (optional, not needed on production)

### 5.2 Create subdomains in cPanel

1. **Domains** → **Subdomains** (or **Domains** → **Create A New Domain** on newer cPanel).
2. **Backend subdomain**
   - Subdomain: `api` → `api.yourdomain.com`
   - Document root: `/home/youruser/backend/public`
3. **Main domain** (customer site)
   - Domain: `yourdomain.com` (or `www.yourdomain.com`)
   - Document root: folder containing React `index.html` (e.g. `/home/youruser/frontend-public`)

### 5.3 Set PHP version for the backend

1. **MultiPHP Manager** (or **Select PHP Version**).
2. Select domain `api.yourdomain.com`.
3. Choose **PHP 8.2** or **8.3**.
4. Enable required extensions listed in section 2.

---

## 6. Deploy the React customer app

1. Upload all files from `frontend/dist/` into the **main domain document root**.
2. Add an `.htaccess` file in that folder so React Router / client routes work:

```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /
  RewriteRule ^index\.html$ - [L]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule . /index.html [L]
</IfModule>
```

3. Visit `https://yourdomain.com` — you should see the booking homepage (API calls may fail until backend is ready).

---

## 7. Configure Laravel `.env` on the server

Edit `backend/.env` on the server (File Manager → Edit, or upload from your PC):

```env
APP_NAME=SonyaBus
APP_ENV=production
APP_KEY=                           # generate in step 8
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

APP_LOCALE=en
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=youruser_sonyabus
DB_USERNAME=youruser_sonyabus_user
DB_PASSWORD=your_strong_password

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_DOMAIN=.yourdomain.com     # optional; helps if you share cookies across subdomains

CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=your_mail_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

# SMS (see SMS_GATEWAY_SETUP.md)
SMS_GATEWAY_API_KEY=
SMS_GATEWAY_SENDER_ID=

# ZiniPay
ZINIPAY_API_KEY=
ZINIPAY_BASE_URL=https://api.zinipay.com
```

**Production rules:**

- `APP_DEBUG=false` always on live sites.
- Never expose `.env` via the web (it must stay outside `public/`).

---

## 8. Run Laravel setup commands

Use **cPanel → Terminal** (SSH) if available:

```bash
cd ~/backend

# Generate application key
php artisan key:generate

# Run migrations and seed demo data (first deploy only)
php artisan migrate --force
php artisan db:seed --force

# Cache config for performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Public storage link (if you use uploaded files / favicon)
php artisan storage:link
```

### If you do not have SSH

Run these on your **local machine** against the **remote MySQL** database (allow remote MySQL in cPanel if needed), then upload the migrated database via **phpMyAdmin**, **or** ask your host to run the commands.

Minimum you must have on the server:

- Valid `APP_KEY` in `.env`
- Database tables created (`migrate`)
- Writable `storage/` and `bootstrap/cache/`

Generate `APP_KEY` locally if needed:

```bash
php artisan key:generate --show
```

Copy the output into `backend/.env` on the server.

---

## 9. File permissions

In **File Manager**, set permissions:

| Path | Permission |
|------|------------|
| `backend/storage/` | **775** (recursive) |
| `backend/bootstrap/cache/` | **775** (recursive) |
| Other Laravel files | **644** files, **755** folders |

If you get **500 errors**, permissions on `storage/` and `bootstrap/cache/` are the first thing to check.

---

## 10. Enable SSL (HTTPS)

1. cPanel → **SSL/TLS Status** or **Let’s Encrypt™ / AutoSSL**.
2. Enable SSL for:
   - `yourdomain.com`
   - `www.yourdomain.com`
   - `api.yourdomain.com`
3. Turn on **Force HTTPS Redirect** (cPanel → **Domains** → **Redirects**, or `.htaccess`).

After SSL is active, rebuild the React app if you built it with `http://` URLs.

---

## 11. Queue worker (SMS notifications)

Bookings can queue SMS jobs. With `QUEUE_CONNECTION=database`, run a worker via **Cron Jobs**:

1. cPanel → **Cron Jobs**
2. Add every minute:

```bash
cd /home/youruser/backend && php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

Or every 5 minutes if traffic is low:

```bash
cd /home/youruser/backend && php artisan queue:work --stop-when-empty --max-time=290 >> /dev/null 2>&1
```

Without this cron job, SMS may stay in the queue and never send.

---

## 12. Third-party services

### 12.1 ZiniPay

In ZiniPay dashboard, set callback URLs to your **Laravel domain**:

- Success: `https://api.yourdomain.com/payment/callback`
- Cancel: `https://api.yourdomain.com/payment/cancel`
- Webhook: `https://api.yourdomain.com/api/payment/webhook`

Set `ZINIPAY_API_KEY` in `backend/.env` and `VITE_ZINIPAY_API_KEY` when building the frontend.

### 12.2 SMS

Configure in `backend/.env` or via admin after login. See `SMS_GATEWAY_SETUP.md`.

### 12.3 Email (password reset)

Use cPanel email account or SMTP (section 7). Test forgot-password on the customer site.

---

## 13. Default logins (after seeding)

Change these immediately on production.

| Role | Email | Password |
|------|-------|----------|
| Super Admin | superadmin@sonyabus.com | password123 |
| Admin | admin@sonyabus.com | password123 |
| Customer | user@sonyabus.com | password123 |

**Admin panel URL:** `https://api.yourdomain.com/admin/login`  
**Customer site:** `https://yourdomain.com`

---

## 14. Post-deployment checklist

```text
[ ] https://yourdomain.com loads the React app
[ ] https://api.yourdomain.com/up returns OK (Laravel health check)
[ ] https://api.yourdomain.com/admin/login opens admin login
[ ] Customer site can load stations (API: /api/stations)
[ ] Register / login works on customer site
[ ] Admin login works
[ ] APP_DEBUG=false in .env
[ ] SSL enabled on both domains
[ ] Cron job for queue:work is active
[ ] Default passwords changed
[ ] ZiniPay test payment completes
[ ] Test email for password reset works
```

Quick API test in browser:

```text
https://api.yourdomain.com/api/site-settings
```

Should return JSON.

---

## 15. Troubleshooting

### Blank page or 500 on Laravel

- Check `backend/storage/logs/laravel.log`
- Fix permissions on `storage/` and `bootstrap/cache/`
- Confirm `APP_KEY` is set
- Confirm PHP 8.2+ and extensions are enabled

### Customer site shows UI but no data

- Open browser **Developer Tools → Network**
- Failed requests to `localhost` mean the frontend was not rebuilt with `VITE_API_BASE_URL`
- CORS errors: ensure API URL uses `https://` and matches `api.yourdomain.com`

### 404 on React routes (refresh breaks page)

- Add the React `.htaccess` from section 6

### Admin login works locally but not on server

- Confirm `SESSION_DRIVER=database` and sessions table exists (`migrate`)
- Check `APP_URL` matches `https://api.yourdomain.com`

### Database connection error

- Verify database name, user, and password in cPanel MySQL
- Use `localhost` as host unless host docs say otherwise

### Composer / artisan not available

- Upload `vendor/` from your PC after `composer install --no-dev`
- Run migrations locally against remote DB, or use phpMyAdmin to import SQL

### Payment callback fails

- Callback URLs must use the **Laravel subdomain**, not the React domain
- Must be **HTTPS** in production

---

## 16. Updating the site later

1. Backup database (cPanel → **phpMyAdmin** → Export).
2. Upload changed backend files (keep `.env`).
3. Upload new React `dist/` files.
4. SSH:

```bash
cd ~/backend
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

5. Clear browser cache and test booking + admin flows.

---

## 17. Optional: single-domain setup (advanced)

If you must use **one domain only** (e.g. `yourdomain.com` for everything), you need extra web server rules to:

- Serve React static files at `/`
- Proxy or route `/api`, `/admin`, and `/payment` to Laravel

This is harder on basic cPanel shared hosting. The **two-subdomain setup in section 1** is the recommended approach for this project.

---

## Quick reference

| What | URL |
|------|-----|
| Customer booking | `https://yourdomain.com` |
| Admin dashboard | `https://api.yourdomain.com/admin` |
| API base | `https://api.yourdomain.com/api` |
| Health check | `https://api.yourdomain.com/up` |
| Payment callback | `https://api.yourdomain.com/payment/callback` |

---

*Replace `yourdomain.com` and `api.yourdomain.com` with your real domains before deploying.*
