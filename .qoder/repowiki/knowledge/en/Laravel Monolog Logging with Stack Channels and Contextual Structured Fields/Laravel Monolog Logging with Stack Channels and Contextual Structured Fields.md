---
kind: logging_system
name: Laravel Monolog Logging with Stack Channels and Contextual Structured Fields
category: logging_system
scope:
    - '**'
source_files:
    - backend/config/logging.php
    - backend/app/Services/SmsGatewayService.php
    - backend/app/Services/ZinipayService.php
    - backend/app/Services/LicenseService.php
    - backend/app/Http/Controllers/PaymentController.php
    - backend/app/Http/Controllers/API/UserAuthController.php
    - backend/app/Jobs/SendBookingSmsNotification.php
---

## System Overview

The SonyaBus backend uses Laravel's built-in logging subsystem, which is a thin wrapper around **Monolog**. There is no custom logger class or third-party logging package; all log output goes through `Illuminate\Support\Facades\Log` (aliased to `Log::`).

## Configuration

- Central configuration lives in `backend/config/logging.php`. It defines the default channel (`stack`, driven by `LOG_CHANNEL` env), deprecations channel (`null` by default), and a set of channels: `stack`, `single`, `daily`, `slack`, `papertrail`, `stderr`, `syslog`, `errorlog`, `null`, and an `emergency` file channel.
- The `stack` channel composes one or more underlying channels via `LOG_STACK` (default `'single'`).
- File sinks write to `storage_path('logs/laravel.log')`; daily rotation defaults to 14 days (`LOG_DAILY_DAYS`).
- Deprecation logging is disabled by default (`LOG_DEPRECATIONS_CHANNEL=null`).
- PSR-3 message placeholder replacement is enabled on file-based channels (`replace_placeholders => true`) so that `Log::info("User {id} logged in", ['id' => $user->id])` renders interpolated strings rather than raw placeholders.
- `PsrLogMessageProcessor` is attached to `papertrail` and `stderr` channels for structured PSR-7/PSR-3 processing.

## Usage Patterns Across the Codebase

All logging calls are made via the `Log` facade. The observed usage pattern is:

1. **Import at top of each file**: `use Illuminate\Support\Facades\Log;`
2. **Level selection** follows semantic intent:
   - `Log::error(...)` — failures in external integrations (ZiniPay API, SMS gateway, license verification, password reset email).
   - `Log::warning(...)` — recoverable or policy violations (incomplete payment callback verification, skipped SMS due to missing config or invalid phone number, failed SMS send).
   - `Log::debug(...)` — diagnostic traces (license DB check skip reason).
   - `Log::info(...)` — normal operational milestones (webhook received, SMS sent successfully).
3. **Structured context as second argument**: every contextual call passes an associative array as the second parameter (e.g. `['error' => $e->getMessage()]`, `array_merge($context, [...])`), producing key/value fields alongside the message. This is the repo's convention for structured fields rather than JSON-encoded payloads.
4. **No per-file or per-module log channels**: all code writes to the global `Log` facade; routing to specific sinks is purely configuration-driven via `config/logging.php`.

## Files Where Logging Is Used

| File | Purpose |
|---|---|
| `backend/config/logging.php` | Channel definitions, sinks, levels, processors |
| `backend/app/Http/Controllers/API/UserAuthController.php` | Password reset email failure logging |
| `backend/app/Http/Controllers/PaymentController.php` | ZiniPay webhook/callback verification logging |
| `backend/app/Jobs/SendBookingSmsNotification.php` | Failed SMS job logging |
| `backend/app/Services/LicenseService.php` | License verification exception / DB-check skip |
| `backend/app/Services/SmsGatewayService.php` | SMS lifecycle (skip, success, rejection, failure) |
| `backend/app/Services/ZinipayService.php` | ZiniPay invoice creation and payment verification errors |

## Conventions Observed

- Log messages are human-readable English sentences; machine-readable details go into the context array, not string concatenation.
- External service failures are consistently logged at `error` level with the exception message captured in the context.
- Skipped operations (SMS not sent because of missing config or invalid phone) use `warning` level with a descriptive reason in context.
- Successful outbound operations (SMS sent, webhook received) use `info` level.
- No custom formatter or processor is registered beyond the stock Monolog handlers and the PSR message processor on `papertrail`/`stderr`.
- The `emergency` channel is defined but does not appear to be used anywhere in application code.
- The same logging setup is mirrored in the CodeCanyon release copy under `sonyabus-codecanyon-v1.0.0/backend/config/logging.php`.