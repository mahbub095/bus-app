# SonyaBus — Bus Ticket Booking System

**Version:** 1.1.0  
**Author:** Your Name  
**License:** CodeCanyon Regular/Extended License  

---

## 📋 Overview

SonyaBus is a full-stack, production-ready bus ticket booking system built with **Laravel 12** (backend API) and **React 19** (customer-facing SPA). It includes an admin dashboard for managing routes, schedules, bookings, and system settings, plus a mobile-first customer app for searching, seat selection, and online payment.

### Key Features

✅ **Envato License Verification** — Built-in installation wizard with CodeCanyon purchase code activation  
✅ **Interactive Seat Map** — Gender-aware seat status (male/female booked/sold), 2-minute hold during checkout  
✅ **Online Payments** — ZiniPay integration with redirect and async webhook support  
✅ **SMS Notifications** — Booking confirmation via SMS.NET.BD, BulkSmsBD, or custom gateway  
✅ **Promo Codes** — Flat discount coupons with expiry and usage limits  
✅ **Role-Based Access Control** — Super Admin, Admin (with menu permissions), and Customer roles  
✅ **11 Report Types** — Sales, revenue, booking analytics with PDF & Excel export  
✅ **Maintenance Mode** — Full-page downtime message from admin panel  
✅ **Dark/Light Theme** — Persistent theme toggle in admin dashboard  
✅ **Security Hardened** — Rate limiting, MIME-based file validation, path traversal prevention, CSRF protection  

---

## 🖥️ Tech Stack

### Backend
- **Laravel 12** (PHP 8.2+)
- **MySQL 8.0+**
- **Laravel Sanctum** for API token authentication
- **Queue** (database driver) for SMS dispatch
- **DomPDF** for PDF ticket generation

### Frontend
- **React 19** (Vite 6)
- **React Router v7**
- **Zustand** for state management
- **Axios** for HTTP requests
- **Framer Motion** for animations

---

## 📦 What's Included

```
sonyabus/
├── backend/                  # Laravel 12 API
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   ├── Admin/        # Admin dashboard controllers
│   │   │   ├── API/          # Customer API controllers
│   │   │   └── Install/      # Installation wizard
│   │   ├── Models/
│   │   ├── Services/
│   │   └── Middleware/
│   ├── database/migrations/
│   ├── resources/views/      # Admin Blade views + install wizard
│   ├── routes/
│   │   ├── api.php           # Customer API routes
│   │   └── web.php           # Admin + install routes
│   ├── .env.example
│   └── composer.json
│
├── frontend/                 # React 19 SPA
│   ├── src/
│   │   ├── pages/            # Search, seat map, checkout, my tickets
│   │   ├── components/
│   │   ├── stores/           # Zustand state
│   │   └── api/
│   ├── .env.example
│   └── package.json
│
├── documentation/            # HTML user guide
│   └── index.html
├── changelog.txt
└── README.md                 # This file
```

---

## 🚀 Quick Start

### 1. Server Requirements

- **PHP:** 8.2 or higher
- **MySQL:** 8.0 or higher
- **Node.js:** 18+ (for frontend build)
- **Composer:** 2.x
- **Web Server:** Nginx or Apache with `mod_rewrite`

**PHP Extensions:** `BCMath`, `Ctype`, `cURL`, `DOM`, `Fileinfo`, `JSON`, `Mbstring`, `OpenSSL`, `PCRE`, `PDO`, `Tokenizer`, `XML`, `GD`

### 2. Installation

**Choose your environment:**

- **Production (cPanel Hosting)** → See `documentation/index.html` → **cPanel Hosting** section
- **Local Development (Laragon / XAMPP)** → See `documentation/index.html` → **Local (Laragon/XAMPP)** section
- **Quick VPS / Cloud Server** → Follow steps below

#### Backend

```bash
cd backend
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate

# Edit .env — set APP_URL, APP_FRONTEND_URL, DB_*, ENVATO_ITEM_ID
# Then set file permissions:
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

#### Frontend

```bash
cd frontend
cp .env.example .env
# Edit .env — set VITE_API_BASE_URL and VITE_ZINIPAY_API_KEY
npm install
npm run build
# Serve frontend/dist/ via your web server
```

#### Web Server

Point backend domain to `backend/public/` and frontend domain to `frontend/dist/`.  
See `documentation/index.html` for full Nginx/Apache configs.

### 3. License Activation

Open your **backend URL** in a browser. You'll be redirected to `/install`. Complete the 4-step wizard:

1. **License** — Enter your Envato purchase code and personal token
2. **Database** — Enter MySQL credentials (live connection test)
3. **Admin Account** — Create super-admin login
4. **Finalize** — Runs migrations and clears cache

After activation, the wizard is inaccessible. The system is ready to use.

---

## 🔑 Envato License

### What You Need

1. **Purchase Code** — from Envato Market → Downloads → License & Purchase Codes
2. **Personal Token** — generate at [build.envato.com/create-token](https://build.envato.com/create-token/?purchase:verify=t) with **Verify Purchases** permission
3. **Item ID** — set `ENVATO_ITEM_ID` in `.env` (numeric ID from your CodeCanyon item URL)

### Re-Verification

If you migrate to a new domain, go to **Admin → License** and re-verify with your purchase code.

### Compliance

- **One purchase = one live installation.** A Regular License covers a single end product for one client.
- For multiple deployments, purchase additional licenses or an Extended License.
- See [Envato License Terms](https://codecanyon.net/licenses/standard) for details.

---

## 📚 Documentation

Open `documentation/index.html` in your browser for the complete user guide, including:

- Installation steps
- .env configuration reference
- Admin panel guide
- Customer app features
- Payment gateway setup
- SMS configuration
- Queue & cron setup
- Troubleshooting

---

## 🔒 Security Features

✅ **Rate Limiting** — 10 requests/min on all auth endpoints (login, register, forgot password)  
✅ **File Upload Validation** — MIME-based extension derivation (prevents `.php.png` spoofing)  
✅ **Path Traversal Prevention** — `EnvFileWriter` validates paths and key names  
✅ **CSRF Protection** — All state-changing routes protected by Laravel's CSRF middleware  
✅ **No Hardcoded Credentials** — `.env.example` is clean; all secrets are environment-driven  
✅ **License Guard Middleware** — Blocks all requests until a valid purchase code is activated  

---

## 🧪 Testing

### Run PHPUnit tests

```bash
cd backend
php artisan test
```

### Optional: Seed demo data

```bash
php artisan db:seed
```

⚠️ **Never run `db:seed` on a production database.** It creates demo accounts with password `password123`.

---

## 🛠️ Default Credentials (after seeding)

| Role         | Email                      | Password      |
|--------------|----------------------------|---------------|
| Super Admin  | superadmin@sonyabus.com    | password123   |
| Admin        | admin@sonyabus.com         | password123   |
| Customer     | customer@sonyabus.com      | password123   |

**⚠️ Change all passwords immediately after setup.**

---

## 📊 What's New in v1.1.0

### Security & License
- Envato purchase code verification via Market API v3
- Installation wizard with 4-step license activation
- License re-verification for domain migrations
- Rate limiting on all auth endpoints
- MIME-based file upload validation (favicon, logo)
- Path traversal protection in `EnvFileWriter`
- Password reset codes no longer logged

### Code Quality
- Removed duplicate `siteSettings`/`smsConfig` fetches in `AdminDashboardService`
- Added `license`, `site-settings`, `gateways` to allowed admin tabs
- Updated `composer.json` name/description for CodeCanyon
- Comprehensive changelog and documentation

---

## 🆘 Support

For installation help, bug reports, or feature requests:

1. Check `documentation/index.html` → **Troubleshooting** section
2. Contact via your CodeCanyon purchase page
3. Email: your-support-email@example.com

---

## 📄 License

This product is licensed under the **Envato Market Regular/Extended License**.  
You may NOT redistribute, resell, or sub-license this product without purchasing additional licenses.

---

## 🙏 Credits

- **Laravel Framework** — [laravel.com](https://laravel.com)
- **React** — [react.dev](https://react.dev)
- **Icons** — Lucide React
- **PDF Generation** — barryvdh/laravel-dompdf

---

**Thank you for purchasing SonyaBus!** 🎉  
We hope this system helps you build a successful bus ticketing business.
