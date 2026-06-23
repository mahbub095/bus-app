# Backend Coding Guide

## Overview

This document describes the backend architecture of the Sonyabus Laravel application, including database relationships, model responsibilities, controller logic, services, routes, and middleware.

The backend is implemented in `backend/` as a Laravel application. It supports:
- Admin dashboard and Blade-based admin web routes
- Public customer API for the React frontend under `backend/routes/api.php`
- Payment handling with ZiniPay
- Booking flow including seat selection, price calculations, and SMS confirmation
- Site settings and maintenance mode management

---

## Directory Structure

Key backend directories:
- `backend/app/Models` — Eloquent models and relationships
- `backend/app/Http/Controllers` — admin controllers and shared controllers
- `backend/app/Http/Controllers/API` — public API controllers
- `backend/app/Http/Middleware` — request guards and maintenance checks
- `backend/app/Services` — reusable business logic services
- `backend/database/migrations` — database schema definitions
- `backend/routes/web.php` — admin web and session-based AJAX routes
- `backend/routes/api.php` — REST API routes consumed by frontend and admin API

---

## Database Schema and Relationships

### Stations
- Table: `stations`
- Fields: `id`, `name`, `district`, timestamps
- Relationships:
  - `departureRoutes()` — hasMany `Route` by `departure_station_id`
  - `arrivalRoutes()` — hasMany `Route` by `arrival_station_id`

### Buses
- Table: `buses`
- Fields: `id`, `operator_name`, `coach_number`, `coach_type`, `total_seats`, `seat_layout`, `seat_layout_grid`, timestamps
- Relationships:
  - `schedules()` — hasMany `Schedule`
- Notes:
  - `seat_layout_grid` is cast to array
  - supports custom seat layout generation in `SeatMapService`

### Routes
- Table: `routes`
- Fields: `id`, `departure_station_id`, `arrival_station_id`, `distance`, `duration`, `boarding_points`, `dropping_points`, timestamps
- Relationships:
  - `departureStation()` — belongsTo `Station` via `departure_station_id`
  - `arrivalStation()` — belongsTo `Station` via `arrival_station_id`
  - `schedules()` — hasMany `Schedule`
- Notes:
  - `boarding_points` and `dropping_points` are stored as JSON arrays and cast to array in the model

### Schedules
- Table: `schedules`
- Fields: `id`, `bus_id`, `route_id`, `departure_time`, `arrival_time`, `fare`, `blocked_seats`, timestamps
- Relationships:
  - `bus()` — belongsTo `Bus`
  - `route()` — belongsTo `Route`
  - `bookings()` — hasMany `Booking`
- Notes:
  - `blocked_seats` is an optional comma-separated string managed by `SeatMapService`

### Bookings
- Table: `bookings`
- Fields: `id`, `schedule_id`, `user_id`, `passenger_name`, `passenger_phone`, `passenger_email`, `passenger_gender`, `boarding_point`, `dropping_point`, `seat_class`, `seat_numbers`, `total_fare`, `payment_method`, `payment_invoice_id`, `status`, timestamps
- Relationships:
  - `schedule()` — belongsTo `Schedule`
  - `user()` — belongsTo `User`
- Notes:
  - `seat_numbers` stores seat labels as comma-separated strings
  - booking `status` values include `PENDING`, `PAID`, `SOLD`, `BOOKED`, `CANCEL_REQUESTED`, `CANCELLED`

### Promotions
- Table: `promotions`
- Fields: `id`, `code`, `discount_amount`, `description`, timestamps

### Users
- `User` model adds:
  - `role` enum with values `admin`, `user`, and additional `super_admin` via migration
  - `menu_permissions` cast as array for admin access control
- Relationships:
  - `bookings()` — hasMany `Booking`
- Helpers:
  - `isAdmin()`, `isSuperAdmin()`, `hasMenuPermission()`

### SiteSettings
- `SiteSetting` model stores key/value pairs for site configuration
- Uses caching for read performance
- `maintenance_mode` and `maintenance_message` are used by `CheckMaintenanceMode`

---

## Key Models and Logic

### `App\Models\Booking`
- Validates booking fields through controller request validation
- Relationship to schedule and user
- Booking status controls seat availability and cancellation workflow

### `App\Models\Schedule`
- Encapsulates a bus trip
- Related route and bus are used to build seat map and pricing

### `App\Models\Route`
- Encapsulates a route connection between stations
- Contains optional boarding and dropping point definitions

### `App\Models\Bus`
- Defines coach metadata and seat layout options
- Generates seat labels through `SeatMapService`

### `App\Models\Station`
- Terminal information for departure and arrival
- Prevents deletion when linked to existing routes

---

## Core Controllers

### `App\Http\Controllers\AuthController`
- `showLogin()` — renders admin login page or redirects logged-in admin
- `login()` — validates credentials and limits access to admin users
- `logout()` — destroys session and regenerates token
- `updatePassword()` — verifies current password and updates admin password

### `App\Http\Controllers\BookingController`
- `store()` — creates admin-side bookings, handles payment logic and SMS sending
- `update()` — updates booking details for admin
- `destroy()` — deletes a booking record
- `cancel()` — submits a cancellation request from admin
- `approveCancelRequest()` — approves cancellation and sets status to `CANCELLED`
- `payAdmin()` — re-creates ZiniPay invoice for pending admin bookings

### `App\Http\Controllers\ScheduleController`
- `store()` — validates schedule creation and creates a schedule record
- `update()` — updates an existing schedule
- `destroy()` — deletes the schedule

### `App\Http\Controllers\RouteController`
- `store()` — validates and creates a new route with boarding/dropping points
- `update()` — updates route data while preventing duplicate station pairs
- `destroy()` — deletes a route
- `validateRoute()` — parses JSON point arrays and ensures at least one boarding and dropping point exist

### `App\Http\Controllers\StationController`
- `store()` — creates a station
- `update()` — updates station name and district with uniqueness validation
- `destroy()` — prevents deletion when station has associated routes

### `App\Http\Controllers\PromotionController`
- Standard CRUD for promotion codes and discount values
- Normalizes code to uppercase

### `App\Http\Controllers\SiteSettingsController`
- `update()` — bulk updates settings from admin form
- `uploadFavicon()` — uploads favicon files and stores URL in settings

### `App\Http\Controllers\PaymentController`
- `callback()` — handles ZiniPay redirect result and verifies payments
- `cancel()` — cancels pending payment bookings and redirects accordingly
- `webhook()` — receives asynchronous ZiniPay webhook events and updates booking status

### `App\Http\Controllers\AdminController`
- `dashboardView()` — prepares summary metrics and lists for admin Blade dashboard
- Loads bookings, routes, schedules, promotions, station/bus lists, site settings, and users

### `App\Http\Controllers\Admin\AjaxController`
- JSON endpoints used by admin AJAX pages
- `searchCoachServices()` — searches schedule availability and seat maps for admin
- `toggleBlockedSeat()` — blocks or unblocks seats on a schedule
- `bookingLogsApi()` — returns recent booking log data
- `cancelRequestsLogsApi()` — returns cancel request log data
- `cancelBookingApi()` — cancels a booking immediately

---

## Public and API Controllers

### `App\Http\Controllers\API\UserAuthController`
- Handles customer registration, login, logout, password reset, and profile actions via Sanctum

### `App\Http\Controllers\API\StationController`
- `index()` — returns station list for frontend search filters

### `App\Http\Controllers\API\SearchController`
- `search()` — finds route schedules by departure station, arrival station, date, and optional coach type
- Returns seat map, pricing, available seats, and route metadata

### `App\Http\Controllers\API\ScheduleController`
- Admin API mirror of schedule operations and seat blocking
- Used by authenticated admin API routes under the `/api/admin/...` prefix

### `App\Http\Controllers\API\PromotionController`
- `index()` — lists promotions
- `check()` — validates promo code and returns discount details

### `App\Http\Controllers\API\BookingController`
- Authenticated customer booking operations
- `store()` — creates frontend bookings for logged-in users
- `mine()` — returns the logged-in user's bookings
- `cancel()` — customer cancellation request workflow
- Admin endpoints for booking logs, cancel requests, approve cancel, and cancel booking
- `showPublic()` — public booking receipt view

---

## Services

### `App\Services\SeatMapService`
- Central seat map and seat booking logic
- Generates seat codes from coach metadata and custom layout grids
- Builds seat map statuses from active bookings and blocked seats
- Offers boarding/dropping point formatting, pricing breakdown, and booking details
- Toggles seat blocking for schedules with validation
- Provides helper scopes for selecting paid or pending bookings

### `App\Services\SmsGatewayService`
- Sends booking verification messages when bookings are confirmed
- Called after successful payment or when admin creates a paid booking

### `App\Services\ZinipayService`
- Creates invoices and verifies payment status with ZiniPay
- Used by both admin and public booking flows

### Report and Excel services
- `ReportDataService`, `ReportFilterService`, and `ExcelExportService` support admin reporting features

---

## Middleware and Request Flow

### `EnsureUserIsAdmin`
- Verifies authentication and admin role
- Redirects non-admin users to login for web requests
- Returns JSON `401`/`403` for API requests

### `EnsureUserHasMenuPermission`
- Verifies admin menu-level permissions stored in `User::menu_permissions`
- Guards route groups like `stations`, `buses`, `routes`, `schedules`, `promotions`, `reports`, `bookings`, and `cancel-requests`

### `EnsureUserIsSuperAdmin`
- Restricts system migration and site settings update actions to super admin users only

### `CheckMaintenanceMode`
- Blocks API routes during maintenance mode except `/api/site-settings`
- Reads `maintenance_mode` and `maintenance_message` from `SiteSetting`

---

## Route Structure

### Web routes (`backend/routes/web.php`)
- Public admin login page and logout
- Session-authenticated admin dashboard and CRUD actions
- AJAX admin routes under `admin/api` for coach services, bookings, and cancel requests
- Super admin-only system migration and site settings routes
- Payment callback and payment cancel endpoints

### API routes (`backend/routes/api.php`)
- Public site settings and search endpoints
- Frontend customer authentication and booking routes
- Authenticated customer routes guarded with `auth:sanctum`
- Authenticated admin API routes guarded with `auth:sanctum`, `admin`, and permission middleware

---

## Booking Flow Summary

1. Customer searches routes by stations and date
2. Backend finds matching `Route`, retrieves `Schedule` and seat availability
3. Seat map is built using `SeatMapService`:
   - seat codes from bus layout or grid
   - blocked seats from `Schedule.blocked_seats`
   - active bookings with `PAID`, `SOLD`, `BOOKED`, or recent `PENDING` status
4. Customer completes booking data and selects payment method
5. Backend validates seat availability and calculates pricing
6. If ZiniPay is selected:
   - booking status becomes `PENDING`
   - invoice is created via `ZinipayService`
   - user is redirected to payment URL
7. Payment callback/webhook updates `Booking.status` to `SOLD` on success
8. SMS confirmation is sent via `SmsGatewayService`

Admin booking flow is similar, but `BOOKED` or `SOLD` statuses may be created directly from the admin dashboard.

---

## Important Coding Notes

- Admin controllers typically use `adminTabRedirect()` to preserve the active dashboard tab and return success/errors in the UI.
- Validation is performed inside controllers using `$request->validate(...)` and custom parsing helpers.
- Business rules are pushed into services where seat and pricing computations are reused.
- The backend uses Eloquent relationships extensively to eager-load nested data such as `schedule.bus` and `schedule.route.departureStation`.
- Route deletion is cascadeable through foreign keys, but `Station` deletion is explicitly prevented if linked to routes.

---

## Useful Files

- `backend/app/Models` — data models and casts
- `backend/app/Http/Controllers` — admin CRUD and payment controllers
- `backend/app/Http/Controllers/API` — public API controllers
- `backend/app/Services/SeatMapService.php` — core seat/booking logic
- `backend/routes/web.php` — admin and payment route definitions
- `backend/routes/api.php` — frontend and admin API definitions
- `backend/database/migrations` — schema and foreign key definitions

---

## Suggested Next Steps

- Add a separate `backend/architecture-diagram.png` if visual schema diagrams are needed
- Document each `API` endpoint with request/response examples when frontend integration is required
- Add unit tests for booking rules, seat map generation, and payment callback handling
