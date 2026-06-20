# SonyaBus — CodeCanyon Release Guide

This document is your checklist for preparing and publishing **SonyaBus** (Laravel admin panel + React customer booking app) on [CodeCanyon](https://codecanyon.net).

Use it in order. Do not submit until every item in **Phase 1** and **Phase 2** is complete.

---

## Product summary (for your listing)

| Item | Value |
|------|-------|
| **Product name** | SonyaBus — Bus Ticket Booking System |
| **Stack** | Laravel 12 (PHP 8.2+), React 19, Vite, MySQL/SQLite |
| **Surfaces** | Customer React app (`/api/*`), Admin Blade dashboard (`/admin/*`) |
| **Integrations** | ZiniPay payments, SMS gateway (SMS.NET.BD), email password reset |
| **Suggested category** | CodeCanyon → PHP Scripts → Miscellaneous (or closest travel/booking category) |

---

## Phase 1 — Fix before packaging (required)

These are blockers. CodeCanyon reviewers and buyers will reject or refund if secrets, localhost URLs, or broken installs remain.

### 1.1 Remove secrets and demo keys

- [ ] **Never ship** `backend/.env` or `frontend/.env`.
- [ ] Remove the hardcoded ZiniPay fallback in `frontend/src/zinipay.js` (line with a literal API key). Buyers must supply their own key via env only.
- [ ] Audit all `*.md` files and delete or redact real API keys (e.g. `SMS_QUICK_START.md` contains a sample SMS key — replace with placeholders).
- [ ] Confirm `.gitignore` excludes `.env`, `vendor/`, `node_modules/`, and build artifacts.

### 1.2 Make URLs configurable (not localhost)

Buyers deploy on their own domains. Hardcoded localhost will break production.

- [ ] Replace `const API_BASE = 'http://localhost:8000/api'` in `frontend/src/App.jsx` with:

  ```js
  const API_BASE = import.meta.env.VITE_API_BASE_URL ?? '/api';
  ```

- [ ] Add `frontend/.env.example`:

  ```env
  VITE_API_BASE_URL=http://localhost:8000/api
  VITE_ZINIPAY_API_KEY=
  ```

- [ ] Replace other hardcoded `http://localhost:8000` references in the frontend with env-based or relative URLs.
- [ ] Set `APP_URL` in `backend/.env.example` and document that it must match the live domain.
- [ ] Restrict `config/cors.php` for production (document allowed origins instead of `*` if possible).

### 1.3 Polish buyer-facing config defaults

- [ ] Update `backend/.env.example`:
  - `APP_NAME=SonyaBus`
  - Clear comments for DB, mail, SMS, and ZiniPay
  - `APP_DEBUG=false` note for production
- [ ] Ensure `backend/database/seeders/DatabaseSeeder.php` demo passwords are documented and safe for demo only (currently `password123` — acceptable for demo, must be documented).

### 1.4 Production-ready defaults

- [ ] Set `APP_DEBUG=false` in production instructions.
- [ ] Document queue worker requirement (`QUEUE_CONNECTION=database` + `php artisan queue:listen`) for SMS jobs.
- [ ] Document `php artisan storage:link` if public file uploads are used.
- [ ] Run `php artisan test` from `backend/` — all tests must pass.
- [ ] Run `npm run build` in `frontend/` and verify the built app works against the API.

### 1.5 Code quality for review

- [ ] Remove dead code, commented-out blocks, and `console.log` debug output.
- [ ] Replace default Laravel `backend/README.md` with a short pointer to main documentation.
- [ ] Do **not** obfuscate or encrypt PHP/JS source — CodeCanyon forbids unreadable code.
- [ ] Ensure third-party licenses are compatible (Laravel MIT, React MIT, dompdf, etc.).

---

## Phase 2 — Buyer documentation (required by CodeCanyon)

CodeCanyon requires clear installation docs. Prepare a **`documentation/`** folder in your release ZIP (HTML or PDF is standard).

### 2.1 Create `documentation/index.html` (main install guide)

Include these sections:

1. **Introduction** — what SonyaBus does, demo URLs, support contact.
2. **Requirements**
   - PHP 8.2+
   - Composer 2.x
   - Node.js 20+ and npm
   - MySQL 8+ (or SQLite for local dev)
   - Apache/Nginx with `mod_rewrite` / try_files
   - PHP extensions: `openssl`, `pdo`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`
3. **Installation (step by step)**
   ```text
   1. Upload/extract the package on your server.
   2. cd backend
   3. cp .env.example .env
   4. composer install --no-dev --optimize-autoloader
   5. php artisan key:generate
   6. Configure .env (DB, APP_URL, mail, SMS, ZiniPay)
   7. php artisan migrate --seed
   8. php artisan storage:link   (if needed)
   9. cd ../frontend
   10. cp .env.example .env
   11. npm install && npm run build
   12. Point web server: API/admin → backend/public, customer app → frontend/dist
   ```
4. **Web server configuration**
   - Nginx/Apache examples for Laravel `public/` as document root.
   - How to serve React `dist/` on the main domain and proxy `/api` to Laravel (or set `VITE_API_BASE_URL`).
5. **Default login credentials** (after seeding)

   | Role | Email | Password |
   |------|-------|----------|
   | Super Admin | superadmin@sonyabus.com | password123 |
   | Admin | admin@sonyabus.com | password123 |
   | Customer | user@sonyabus.com | password123 |

   Add a warning: change all passwords immediately on production.

6. **Admin panel** — URL `/admin/login`, role permissions, menu permissions.
7. **Customer app** — search, booking, seat map, My Tickets, promotions.
8. **Payment setup (ZiniPay)** — backend `ZINIPAY_*` vars, frontend `VITE_ZINIPAY_API_KEY`, callback URLs:
   - `/payment/callback`
   - `/payment/cancel`
   - API webhook route (document from `routes/api.php`).
9. **SMS setup** — reference `SMS_GATEWAY_SETUP.md` content (sanitized, no real keys).
10. **Mail setup** — password reset requires working SMTP.
11. **Cron / queue** — SMS and jobs:
    ```bash
    php artisan queue:work --tries=3
    ```
12. **Updating** — backup DB, replace files, `composer install`, `php artisan migrate`, rebuild frontend.
13. **Troubleshooting** — 500 errors (permissions on `storage/`, `bootstrap/cache/`), CORS, blank React page (wrong `VITE_API_BASE_URL`), payment callback mismatch.

### 2.2 Supporting files in the ZIP root

- [ ] `changelog.txt` — version history (start with `1.0.0`).
- [ ] `license.txt` — state that buyers receive the [Envato Regular License](https://codecanyon.net/licenses/standard) (or Extended if you offer it). List major third-party libraries and their licenses.
- [ ] Optional: `documentation/assets/` — screenshots referenced from the HTML guide.

### 2.3 Internal docs — what to include vs exclude

**Include for buyers** (copy into `documentation/` or link from index):

- Installation and configuration sections above
- SMS gateway setup (sanitized)
- Admin feature overview

**Exclude from buyer ZIP** (author-only / optional bonus):

- `k6/` load-testing scripts
- `SECURITY_AUDIT_REPORT.md`, internal optimization notes
- `backend/BACKEND_CODING_GUIDE.md`, `backend/ARCHITECTURE.md` (optional “developer docs” add-on)

---

## Phase 3 — Demo site (strongly recommended)

CodeCanyon approval rates are much higher with a working live demo.

### 3.1 Host a demo

- [ ] Deploy backend + frontend on a subdomain (e.g. `demo.yourdomain.com`).
- [ ] Run migrations with seed data.
- [ ] Set `APP_DEBUG=false`, disable dangerous super-admin tools if needed (e.g. in-app migrations).
- [ ] Use **test/sandbox** ZiniPay keys only.
- [ ] Reset demo data daily or weekly (cron + `migrate:fresh --seed` on a staging DB).
- [ ] Add a visible banner: “Demo — data resets periodically.”

### 3.2 Demo credentials page

- [ ] Public page or documentation section listing admin and customer logins.
- [ ] Do not use real customer PII or real payment cards.

---

## Phase 4 — Marketing assets for CodeCanyon

Prepare before opening the submission form.

### 4.1 Item description

Write for buyers, not developers:

- **Headline** — Bus ticket booking with seat selection, admin dashboard, payments, SMS.
- **Bullet features** — online booking, seat map, PNR tickets, promotions, reports (PDF/Excel), role-based admin, payment gateway, SMS notifications.
- **Tech highlights** — Laravel 12, React, responsive UI, REST API.
- **What’s included** — full source, documentation, demo data seeder.
- **Support policy** — e.g. 6 months support, 24–48 h response via CodeCanyon comments.

### 4.2 Images and media

| Asset | Size / notes |
|-------|----------------|
| **Thumbnail** | 80×80 px |
| **Preview image** | 590×300 px |
| **Screenshots** | 1280×720 or similar — home, search, seat map, checkout, admin dashboard, reports |
| **Optional video** | 60–120 s walkthrough (YouTube/Vimeo link) |

Screenshot checklist:

- [ ] Customer homepage / search
- [ ] Schedule list and seat selection
- [ ] Booking confirmation / ticket
- [ ] Admin dashboard
- [ ] Bookings management
- [ ] Reports export
- [ ] Mobile-responsive view

### 4.3 Tags and SEO

Example tags: `bus booking`, `ticket reservation`, `laravel`, `react`, `transport`, `seat map`, `payment gateway`, `admin panel`.

---

## Phase 5 — Release ZIP structure

CodeCanyon expects a clean, installable package. Recommended layout:

```text
sonyabus-codecanyon-v1.0.0/
├── changelog.txt
├── license.txt
├── documentation/
│   ├── index.html
│   └── assets/          # screenshots for the guide
├── backend/
│   ├── app/
│   ├── config/
│   ├── database/
│   ├── public/
│   ├── routes/
│   ├── .env.example
│   ├── composer.json
│   └── ...              # no vendor/, no .env
└── frontend/
    ├── src/
    ├── public/
    ├── .env.example
    ├── package.json
    └── ...              # no node_modules/, no dist/ (buyer builds)
```

### Packaging commands (run locally)

```bash
# From repo root — adjust folder name/version
# Ensure vendor, node_modules, .env, and .git are NOT included

cd backend
composer install --no-dev --optimize-autoloader   # verify install works, then delete vendor before zipping OR exclude vendor and document composer install

cd ../frontend
npm ci && npm run build   # verify build works, then delete node_modules and dist before zipping
```

**Standard practice:** ship **without** `vendor/` and `node_modules/`; document `composer install` and `npm install` in `documentation/index.html`.

### File permissions (document for buyers)

```text
storage/                 → writable (775)
bootstrap/cache/         → writable (775)
backend/.env             → not web-accessible
```

---

## Phase 6 — CodeCanyon submission

1. **Create an Envato author account** at [author.envato.com](https://author.envato.com) if you do not have one.
2. **Submit a new item** → CodeCanyon → Upload ZIP.
3. **Fill in listing fields** — title, description, price, category, tags, demo URL, documentation location (`documentation/index.html`).
4. **Set pricing** — research similar booking/Laravel items; typical range $29–$79 for niche scripts (adjust based on features).
5. **Choose license** — Regular License (single end product); offer Extended License if buyers may charge end users (SaaS).
6. **Soft rejection loop** — Envato may request better docs, unique demo, or code changes. Respond with updated ZIP and changelog.

### Common rejection reasons (avoid these)

- Missing or unclear installation steps
- Hardcoded URLs or credentials in source
- Broken demo or demo equals localhost instructions only
- Default Laravel readme with no product-specific docs
- GPL/conflicting licensing not explained
- “As-is” with no support statement

---

## Phase 7 — After approval

- [ ] Monitor CodeCanyon comments and support requests.
- [ ] Tag releases in git (`v1.0.0`, `v1.0.1`).
- [ ] For updates: bump `changelog.txt`, upload new ZIP, notify buyers via Envato.
- [ ] Keep a private repo for development; publish only release branches to the marketplace ZIP.

---

## Pre-submission quick checklist

Copy this into your issue tracker and tick each box:

```
[ ] No .env or API keys in source or docs
[ ] Frontend uses VITE_API_BASE_URL (no hardcoded localhost)
[ ] backend/.env.example and frontend/.env.example complete
[ ] documentation/index.html finished
[ ] changelog.txt and license.txt added
[ ] php artisan test passes
[ ] npm run build succeeds
[ ] Live demo deployed (recommended)
[ ] Screenshots and thumbnail ready
[ ] Release ZIP matches structure above
[ ] Demo passwords documented with “change on production” warning
```

---

## Related project docs (author reference)

When writing buyer documentation, pull accurate detail from:

| Topic | File |
|-------|------|
| Backend structure | `backend/ARCHITECTURE.md` |
| SMS | `SMS_GATEWAY_SETUP.md`, `SMS_QUICK_START.md` |
| Security hardening | `SECURITY_IMPLEMENTATION_GUIDE.md` |
| Frontend API usage | `frontend/FRONTEND_CODING_GUIDE.md` |

Sanitize all of these before copying text into public buyer docs.

---

## Support statement template (for listing)

> Support is provided for installation and documented features via CodeCanyon comments for 6 months from purchase. Customization, hosting setup, and third-party gateway accounts (ZiniPay, SMS provider, SMTP) are the buyer’s responsibility. Always backup your database before updating.

---

*Last updated for SonyaBus codebase layout (Laravel backend + React frontend). Adjust version numbers and URLs when you publish.*
