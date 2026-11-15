---
kind: error_handling
name: Laravel Exception + Service-Level Logging with Middleware-Driven Maintenance Mode
category: error_handling
scope:
    - '**'
source_files:
    - backend/app/Providers/AppServiceProvider.php
    - backend/app/Http/Middleware/CheckMaintenanceMode.php
    - backend/app/Services/ZinipayService.php
    - backend/app/Services/SeatMapService.php
    - backend/app/Services/BookingService.php
    - backend/app/Services/LicenseService.php
    - backend/app/Services/SmsGatewayService.php
    - backend/app/Services/GatewaySettingsService.php
    - backend/app/Console/Commands/InstallFinalize.php
    - backend/app/Http/Controllers/API/UserAuthController.php
    - backend/app/Http/Controllers/Admin/AjaxController.php
    - backend/app/Http/Controllers/Admin/ReportController.php
    - frontend/src/App.jsx
    - frontend/src/components/BookingPortal.jsx
---

## Overview

The SonyaBus platform uses a **mixed, mostly ad-hoc error-handling approach** built on top of Laravel's default exception handling. There is no centralized custom exception hierarchy, no global exception handler override, and no sentinel error types. Errors surface through three channels:

1. PHP exceptions thrown from services / install commands.
2. `abort()` calls (only one observed).
3. Service methods returning `null` or error payloads after logging via `Log::error()`.

The only cross-cutting HTTP-level concern is the `CheckMaintenanceMode` middleware, which short-circuits requests with a `503` JSON response when maintenance mode is enabled.

## Backend (Laravel)

### No custom exception classes

A repository-wide search for `class \w+Exception|extends Exception` in `backend/app/**/*.php` returns zero results — there are no application-defined exception classes. Where domain validation fails, code throws built-in PHP exceptions directly:

- `SeatMapService::blockSeats()` throws `\InvalidArgumentException('Invalid seat code.')` and `\InvalidArgumentException('Only available seats can be blocked.')` (lines 465, 487).
- `BookingService::createPaymentInvoice()` throws `\Exception('Failed to create payment invoice with ZiniPay.')` (line 301).
- Install commands throw `\RuntimeException` for database connection failures, admin creation failures, and SQL failures (`InstallFinalize.php`, `InstallController.php`).

These are not caught by any application-level handler; they bubble up to Laravel's default exception renderer, which renders a stack trace in development and a generic error page in production.

### Service-layer pattern: try/catch/Log + null return

External-facing service methods consistently wrap I/O in `try { ... } catch (\Throwable $e) { Log::error(...); }` and return a sentinel value rather than rethrowing:

- `ZinipayService::createInvoice()` returns `?array`; logs API key missing, unsuccessful response, and exceptions, returning `null` on failure.
- `ZinipayService::verifyPayment()` returns `?string`; same pattern.
- `LicenseService`, `SmsGatewayService`, `GatewaySettingsService`, `CoachServicesService` follow the same shape.

This makes callers responsible for checking for `null` (or other sentinel values) instead of relying on exceptions.

### Controller-level responses

Controllers do not share an error-response helper. Successful responses use `response()->json(...)`. Error responses are ad-hoc:

- `UserAuthController` returns `{ message: '...' }` with various status codes inline.
- `AjaxController` returns `{ message }` with status `422` for validation-like failures.
- `ReportController` uses Laravel's `abort(404)` — the only observed use of `abort()` in the codebase.
- `BaseController` and the base `Controller` class are empty stubs and provide no shared error formatting.

### Maintenance-mode middleware as the only cross-cutting HTTP error path

`App\Http\Middleware\CheckMaintenanceMode` intercepts every request, excludes `api/site-settings` and `api/site-settings/*`, reads `maintenance_mode` / `maintenance_message` from `SiteSetting`, and returns:

```json
{"maintenance": true, "message": "..."}
```
with HTTP status `503`.

### Boot-time error swallowing

`AppServiceProvider::boot()` wraps all DB-dependent setup (site settings, mail config overrides, SMS config sharing) in `try { ... } catch (\Exception $e) {}` blocks so that the app boots even when the database is unavailable during installation/migration. This is deliberate per the comment: "Ignore database connection issues during migration / seeding phase".

### Frontend (React SPA)

Error handling is component-scoped with local `try/catch` blocks around fetch calls. Errors are typically logged or surfaced via a shared `Toast` component; there is no global Axios/fetch interceptor, no error boundary, and no central error reducer. Examples include `App.jsx`, `BookingPortal.jsx`, `MyTickets.jsx`, `UserProfile.jsx`, `OffersList.jsx`, and `SeatMap.jsx`.

## Conventions Observed

- Domain validation errors are expressed as `\InvalidArgumentException` thrown from services (e.g., seat blocking).
- External I/O failures (payment gateway, SMS, license server) are swallowed inside services, logged via `Log::error()`, and represented by `null` return values.
- Installation / CLI paths prefer `\RuntimeException` with a human-readable message.
- The only HTTP-level sentinel error is the `503` maintenance-mode response produced by `CheckMaintenanceMode`.
- Controllers have no shared error formatter; each endpoint constructs its own JSON payload.
- Boot-time failures are intentionally suppressed so the installer can run against an empty database.
- Frontend errors are handled locally per component with `try/catch` and rendered via a `Toast` UI component.

## Rules Enforced by Code Structure

- There is no framework-enforced rule requiring custom exception classes; none exist.
- There is no global exception handler registered in `AppServiceProvider` or elsewhere in the visible codebase, so uncaught exceptions fall through to Laravel's defaults.
- The `CheckMaintenanceMode` middleware is the sole place where a structured `503` JSON error response is produced across the request pipeline.
- Service methods that perform network I/O are expected to return `null` on failure rather than throw, based on the consistent pattern in `ZinipayService`, `SmsGatewayService`, `LicenseService`, and `GatewaySettingsService`.