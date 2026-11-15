---
kind: configuration_system
name: Laravel .env + Config Files + Database-Backed Site Settings
category: configuration_system
scope:
    - '**'
source_files:
    - backend/.env.example
    - backend/config/app.php
    - backend/config/mail.php
    - backend/config/booking.php
    - backend/config/sms.php
    - backend/config/envato.php
    - backend/app/Models/SiteSetting.php
    - backend/app/Services/SiteSettingsService.php
    - backend/app/Models/SmsConfig.php
    - backend/app/Services/EnvFileWriter.php
    - backend/app/Http/Middleware/CheckMaintenanceMode.php
    - backend/app/Http/Controllers/Install/InstallController.php
---

## Approach

SonyaBus uses the standard Laravel configuration system as its foundation, with two additional runtime layers:

1. **Framework config files** under `backend/config/` — static defaults for app, database, mail, queue, cache, session, logging, CORS, Sanctum, filesystems, and domain-specific settings (`booking.php`, `sms.php`, `envato.php`).
2. **`.env` / `.env.example`** — per-installation secrets and environment toggles loaded via `env()` calls in the config files.
3. **Database-backed site settings** — a key/value store (`site_settings` table, `SiteSetting` model) used for settings that must be changeable at runtime through the admin UI (site title, logo/favicon URLs, footer links, SEO metadata, maintenance mode/message).
4. **SMS gateway configuration** — persisted separately in an `sms_configs` table via the `SmsConfig` Eloquent model, separate from the static `config/sms.php` which holds the default driver.

## Key Files

- `backend/.env.example` — canonical template of every supported environment variable (APP_*, ENVATO_*, DB_*, SESSION_*, QUEUE_*, CACHE_*, MAIL_*, ZINIPAY_*, SMS_GATEWAY_*, BOOKING_*).
- `backend/config/app.php` — application name, env, debug, URL, locale, timezone, encryption key, previous keys, maintenance driver/store; also exposes `installed`, `licensed`, `envato_skip_verify` flags read via `config()` so they work with `config:cache`.
- `backend/config/mail.php` — default mailer selected by `MAIL_MAILER`; SMTP, SES, Postmark, Resend, Sendmail, Log, Array, Failover, Roundrobin transports configured here.
- `backend/config/booking.php` — service/gateway charge amounts, discount amounts, default seat class, and seat row alphabet.
- `backend/config/sms.php` — default SMS gateway driver, API URL, API key (falls back to `SMS_NETBD_API_KEY`), sender ID, active flag, message template.
- `backend/config/envato.php` — CodeCanyon item ID and author personal token for license verification.
- `backend/app/Models/SiteSetting.php` — key/value Eloquent model with `getValue`, `setValue`, `setMany`, `getAll`, `getAllCached`, `clearCache`; caches all settings under key `site_settings_all` for 300 seconds.
- `backend/app/Services/SiteSettingsService.php` — orchestrates reading/writing site settings, validates admin requests, uploads favicon/logo to `public/uploads`, and exposes a public API array consumed by the frontend.
- `backend/app/Models/SmsConfig.php` — Eloquent model for per-installation SMS gateway overrides.
- `backend/app/Services/EnvFileWriter.php` — safe writer that updates `.env` key/value pairs during installation.
- `backend/app/Http/Middleware/CheckMaintenanceMode.php` — middleware that reads `maintenance_mode`/`maintenance_message` from `SiteSetting` and returns 503 JSON when enabled; excludes `api/site-settings*` so the frontend can still poll status.
- `backend/app/Http/Controllers/Install/InstallController.php` — three-step installer (license → database → finalize) that writes DB credentials directly into `.env` via `EnvFileWriter` and launches background Artisan commands for migration/admin creation.

## Architecture and Conventions

### Layering order

The effective value of a setting is resolved in this order (observed from code):

1. Environment variable via `env('VAR', default)` in a `config/*.php` file.
2. Default value hardcoded in the config file.
3. For runtime-writable settings, the `SiteSetting` model reads from the `site_settings` table (cached 5 minutes).
4. For SMS gateway settings, the `SmsConfig` model provides per-installation overrides on top of `config/sms.php` defaults.

### Static vs. runtime configuration

- **Static config** (never changes after deploy): database connection, mail transport, queue/cache/session drivers, booking charges, Envato tokens — all live in `config/*.php` and are read through `env()`.
- **Runtime config** (editable from Admin UI): site title, logos, footer links, SEO fields, maintenance toggle/message — stored in the `site_settings` table and accessed through `SiteSetting::getValue` / `SiteSettingsService`.
- **Feature flags**: `APP_INSTALLED` and `APP_LICENSED` are boolean-like flags read via `config('app.installed')` / `config('app.licensed')` (not `env()`) so they survive `php artisan config:cache`. The installer sets them by writing `.env` and then re-caching.

### Installation flow

`InstallController` drives a state machine across three HTTP steps plus a background finalize step:

1. `/install` — verify purchase code against Envato Market API (using `ENVATO_ITEM_ID` + `ENVATO_PERSONAL_TOKEN`); supports `ENVATO_SKIP_VERIFY=true` for local dev when `APP_ENV=local`.
2. `/install/database` — raw PDO connection test, then `EnvFileWriter::set()` writes `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` into `.env`; optional SQL dump import runs inline.
3. `/install/finalize/run` — stores admin credentials and pending license data in Laravel cache (10-minute TTL), writes a status JSON file under `storage/framework/install-finalize-status.json`, and spawns a background `artisan install:finalize --token=<random>` process (Windows `start /B`, Unix `exec ... &`).
4. `/install/finalize/status` — polls the status file until completion.

### Maintenance mode

Two independent mechanisms coexist:

- Laravel's built-in maintenance mode (`APP_MAINTENANCE_DRIVER=file|cache`, `APP_MAINTENANCE_STORE=database`) controlled by `config/app.php`.
- Application-level maintenance mode driven by `SiteSetting::getValue('maintenance_mode')` in `CheckMaintenanceMode` middleware, returning `{"maintenance": true, "message": ...}` with HTTP 503. The `api/site-settings` endpoint is whitelisted so the SPA can still fetch the current state.

### Security conventions around `.env`

- `EnvFileWriter::set()` validates every key against `/^[A-Z_][A-Z0-9_]*$/i` before writing — malformed keys are silently skipped.
- It resolves `realpath(base_path('.env'))` and rejects any path not starting with `realpath(base_path())` to prevent path traversal.
- Values containing whitespace or `{`/`}` are double-quoted with escaped inner quotes.
- `.env` itself is gitignored; only `.env.example` is committed.

### Frontend integration

The React frontend (`frontend/.env` and `frontend/.env.example`) mirrors a subset of backend variables (e.g. `VITE_APP_NAME` is set from `${APP_NAME}` in `backend/.env.example`). The frontend consumes runtime settings through the `api/site-settings` endpoint served by `SiteSettingsService::toPublicApiArray()`.

## Conventions and Constraints

- Every configurable value has a default in `config/*.php` and a documented counterpart in `.env.example` — adding a new setting means touching both files.
- Boolean-like flags exposed to PHP code via `config()` (not `env()`) so they remain available after `config:cache`; values are normalized by accepting `true`, `1`, or the string `'true'` (see `InstallController::isInstallLocked` / `hasValidLicense`).
- Runtime-editable settings go through `SiteSetting::setValue` / `setMany` followed by `SiteSetting::clearCache()` — never direct DB writes bypassing these helpers.
- SMS gateway credentials are persisted in the `sms_configs` table rather than `.env`, allowing per-installation override of the static `config/sms.php` defaults.
- File uploads for logo/favicon are validated by MIME type mapped to a whitelist of extensions, then saved under `public/uploads/` and recorded as a URL in `SiteSetting`.
- The installer never touches the application database before the user explicitly confirms DB credentials; it uses a raw PDO connection first, then writes `.env`, then triggers migrations through the background command.