# JourneyGo — Backend (Laravel 12)

Laravel 12 API + admin dashboard for the JourneyGo bus ticket booking system.

This application serves two surfaces from one codebase:

| Surface | Entry point | Auth |
|---------|-------------|------|
| Customer JSON API (consumed by `frontend/`) | `/api/*` | Laravel Sanctum bearer token |
| Admin dashboard (Blade + Vite assets) | `/admin/*` | Session guard |

Both surfaces share the same domain layer (`app/Services`, `app/Models`).

---

## Requirements

- PHP **8.2+** with `BCMath`, `Ctype`, `cURL`, `DOM`, `Fileinfo`, `GD`, `JSON`, `Mbstring`, `OpenSSL`, `PDO`, `Tokenizer`, `XML`
- MySQL **8.0+**
- Composer **2.x**
- Node.js **20.19+** (only needed if you recompile the admin assets)

## Install

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```dotenv
APP_URL=https://api.yourdomain.com          # backend public URL
APP_FRONTEND_URL=https://yourdomain.com     # React app URL (CORS + redirects)
DB_DATABASE=sonyabus
DB_USERNAME=...
DB_PASSWORD=...
ENVATO_ITEM_ID=...                          # CodeCanyon item ID
```

Then open `APP_URL` in a browser. The installer at `/install` walks through
license → database → finalize (creates the super admin and runs migrations).

Writable paths: `storage/` and `bootstrap/cache/`.

```bash
chmod -R 775 storage bootstrap/cache
```

## Admin assets

Compiled admin assets are shipped in `public/build/`. Rebuild them only after
editing `resources/css` or `resources/js`:

```bash
npm install
npm run build
```

## Queue

Booking confirmation SMS and mail are queued. Either run a worker or point the
queue at `sync` for low-volume installs:

```bash
php artisan queue:work --tries=3
```

`QUEUE_CONNECTION=database` (default) requires the `jobs` table, which the
migrations create.

## Scheduled tasks

`routes/console.php` registers the seat-hold cleanup and report cache warmers.
Add the Laravel scheduler to cron:

```
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

## Tests

```bash
php artisan test
```

The suite runs against an in-memory SQLite database (`phpunit.xml`), so no
external database is required.

## Directory map

```
app/
├── Console/Commands/     # install:finalize, install:demo-seed, factory reset
├── Http/Controllers/
│   ├── Admin/            # Blade admin dashboard controllers
│   ├── API/              # Sanctum customer API controllers
│   └── Install/          # Installation wizard
├── Models/               # Eloquent models
├── Services/             # Shared domain logic (booking, reports, settings, gateways)
config/                   # Includes booking.php, envato.php, sms.php
database/migrations/      # Schema
resources/views/          # Blade views (admin + installer)
routes/api.php            # Customer API
routes/web.php            # Admin, installer, payment callbacks
```

See `ARCHITECTURE.md` for the service/controller boundaries and
`BACKEND_CODING_GUIDE.md` for code conventions.

## CORS and Sanctum

`config/cors.php` allows credentialed requests from `APP_FRONTEND_URL`.
Token authentication means `SANCTUM_STATEFUL_DOMAINS` can stay `none` for the
API-only flow.
