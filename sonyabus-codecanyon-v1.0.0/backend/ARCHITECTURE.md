# SonyaBus Backend Architecture

This document explains how the Laravel backend is organized so you can find code quickly.

## Two entry points

| Surface | Routes file | Controllers | Auth |
|---------|-------------|-------------|------|
| **Customer React app** | `routes/api.php` | `App\Http\Controllers\API\*` | Sanctum token |
| **Admin Blade dashboard** | `routes/web.php` | `App\Http\Controllers\Admin\*` | Session cookie |
| **Payment callbacks** | `routes/web.php` | `PaymentController` | Public (signed URLs) |

The React frontend talks to `/api/*`. The admin panel uses form POSTs and `/admin/api/*` JSON endpoints with session auth — not the public Sanctum admin routes (those were removed).

## Folder layout

```
backend/
├── app/
│   ├── Http/Controllers/
│   │   ├── Admin/              ← Admin panel (Blade + session AJAX)
│   │   │   ├── BaseAdminController.php   # tab redirects after form submit
│   │   │   ├── DashboardController.php   # main /admin view
│   │   │   ├── AjaxController.php        # /admin/api/* JSON
│   │   │   ├── BookingController.php     # thin — delegates to services
│   │   │   └── … CRUD controllers
│   │   ├── API/                ← Customer JSON API (Sanctum)
│   │   └── PaymentController.php  # ZiniPay return URLs (public)
│   ├── Models/                 ← Eloquent models (Booking, Schedule, …)
│   └── Services/               ← Business logic (prefer adding here)
├── routes/
│   ├── api.php                 ← Customer API
│   └── web.php                 ← Admin + payment callbacks
└── tests/                      ← PHPUnit feature tests
```

**Rule of thumb:** Controllers validate input and return HTTP responses. Services hold reusable logic.

## Key services

| Service | Purpose |
|---------|---------|
| `BookingService` | Create/cancel bookings, format ticket JSON (shared by API + admin) |
| `AdminBookingService` | Admin cancel approval, booking logs |
| `AdminDashboardService` | Data for the admin dashboard Blade view |
| `CoachServicesService` | Coach search + seat blocking for admin |
| `SeatMapService` | Seat layout, pricing, availability |
| `RouteService` | Route form validation + duplicate checks |
| `SiteSettingsService` | Public settings JSON + admin form updates |
| `ZinipayService` | Payment gateway invoices |
| `SmsGatewayService` | Booking confirmation SMS |
| `ReportDataService` / `ReportFilterService` | Admin reports |

## Request flow examples

### Customer books a ticket

```
React → POST /api/bookings (Sanctum)
     → API\BookingController::store
     → BookingService::createForCustomer (locks seats, creates row)
     → JSON ticket or payment_url
```

### Admin creates a booking from dashboard

```
Blade form → POST /admin/bookings (session)
          → Admin\BookingController::store
          → BookingService::createForAdmin
          → redirect or JSON
```

### Admin dashboard AJAX

```
Blade JS → GET /admin/api/bookings/logs (session)
        → Admin\AjaxController
        → AdminBookingService
        → JSON
```

## Models

- `Booking` — ticket record; `$booking->pnr` gives the passenger reference (e.g. `SE00123`)
- `Schedule` — a bus run on a route at a date/time
- `Route` — departure/arrival stations + boarding/dropping points
- `Bus` — coach fleet + seat layout
- `User` — customers and admins (`isAdmin()`, `isSuperAdmin()`)

## Middleware

- `auth` + `admin` — admin panel access
- `menu_permission:*` — per-tab access for non–super-admin users
- `super_admin` — site settings, migrations
- Sanctum on `/api/*` for customer routes that need login

## Where to change things

| Task | Start here |
|------|------------|
| New customer API endpoint | `routes/api.php` → `API\*Controller` → Service |
| New admin form action | `routes/web.php` → `Admin\*Controller` → Service |
| Booking rules / pricing | `BookingService`, `SeatMapService` |
| Seat map layout | `SeatMapService`, `Bus` model |
| Admin dashboard data | `AdminDashboardService` |
| Reports | `ReportDataService`, `Admin\ReportController` |

## Tests

From `backend/`:

```bash
php artisan test
```

Tests hit real routes; after moving controllers, route names stay the same so existing tests should keep passing.
