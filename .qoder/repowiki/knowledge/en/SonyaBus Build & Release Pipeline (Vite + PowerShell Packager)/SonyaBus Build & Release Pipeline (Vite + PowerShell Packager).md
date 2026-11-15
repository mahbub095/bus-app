---
kind: build_system
name: SonyaBus Build & Release Pipeline (Vite + PowerShell Packager)
category: build_system
scope:
    - '**'
source_files:
    - build-release.ps1
    - build-frontend.bat
    - backend/build.bat
    - backend/package.json
    - backend/vite.config.js
    - frontend/package.json
    - frontend/vite.config.js
    - backend/phpunit.xml
    - k6/scenarios/load.js
    - k6/config/index.js
---

## Overview

SonyaBus is a dual-component application with no CI/CD or containerization in the repository. The build system consists of:

- **Laravel backend** — PHP via Composer, Blade views, and Vite for admin assets.
- **React customer frontend** — standalone Vite SPA under `frontend/`.
- **k6 performance tests** — separate from the app build.
- **PowerShell release packager** — the only cross-cutting orchestration script.

There are no Dockerfiles, Makefiles, GitHub Actions/GitLab CI, Jenkinsfile, CircleCI, Travis, or any other CI configuration files anywhere in the repo (grep across the entire tree returned zero matches).

## Backend asset build

The backend ships its own `package.json` (`backend/package.json`) with Vite as the asset pipeline:

- Scripts: `npm run build` → `vite build`, `npm run dev` → `vite`.
- Entry points declared in `backend/vite.config.js`: `resources/css/app.css` plus one entry per admin JS module (`layout.js`, `dashboard-overview.js`, `bookings.js`, `buses.js`, `cancel-requests.js`, `coach-services.js`, `gateway-settings.js`, `reports.js`, `report-detail.js`, `routes.js`, `site-settings.js`, `users.js`).
- Tailwind v4 via `@tailwindcss/vite` plugin.
- Laravel Vite plugin with HMR (`refresh: true`) and ignores compiled Blade views (`storage/framework/views/**`).

Two Windows batch helpers exist for local builds:

- `build-frontend.bat` — hardcodes Laragon paths (`C:\laragon\bin\nodejs\node-v18\node.exe`, `C:\laragon\www\bus-app\backend`), runs `npm install` then `npm run build`, and expects output at `public/build/`.
- `backend/build.bat` — another Laragon-specific shortcut that invokes `node node_modules/vite/bin/vite.js build` directly.

These `.bat` files are development-only conveniences; they pin absolute paths on the author's machine and should not be treated as portable build instructions.

## Frontend asset build

The React SPA lives in `frontend/` with its own `package.json`:

- Scripts: `dev` (`vite`), `build` (`vite build`), `lint` (`eslint .`), `preview` (`vite preview`).
- Uses `@vitejs/plugin-react`, ESLint (flat config via `eslint.config.js`), and React 19.
- `frontend/vite.config.js` configures a dev server on port 5173 with `host: true` and proxies `/api` and `/uploads` to the backend target (`VITE_API_BASE_URL`, defaulting to `http://localhost:8000`). It also includes Laragon/Apache subdirectory fallback proxies (`/backend/public/api`, `/bus-app/backend/public/api`, `/backend/public/uploads`) so the SPA works without an explicit backend URL override.

The frontend does not ship a production build artifact in the repo — `frontend/dist/` is empty and excluded by `.gitignore`; it must be built locally before deployment.

## Testing

- **PHPUnit**: `backend/phpunit.xml` defines two suites (`Unit` under `tests/Unit`, `Feature` under `tests/Feature`), bootstraps `vendor/autoload.php`, and sets testing defaults (SQLite in-memory DB, array cache/session/mail/queue drivers, `APP_ENV=testing`, reduced bcrypt rounds).
- **k6**: Load/performance tests live under `k6/` with scenario runners (`scenarios/*.js`), reusable libraries (`lib/*.js`), shared flows (`flows/*.js`), and a config file (`config/index.js`). These are executed independently via the k6 CLI, not through Composer/npm scripts.

## Release packaging

The sole cross-cutting build/release script is `build-release.ps1` (run from the repo root):

1. Defines `$VERSION = "1.0.0"` and constructs `$RELEASE = "sonyabus-codecanyon-v$VERSION"`.
2. Creates a staging directory `sonyabus-codecanyon-v1.0.0/`.
3. Copies `backend/` excluding `vendor`, `.env`, `node_modules`, `.phpunit.result.cache`, `storage/logs`, `storage/framework/{cache,sessions,views,installed,install-status.json,install-finalize-status.json,demo-seed-status.json}`, and `bootstrap/cache/*.php`. It then recreates writable directories (`storage/logs`, `storage/framework/cache/data`, `storage/framework/sessions`, `storage/framework/views`, `bootstrap/cache`) and drops `.gitkeep` files into them.
4. Copies `frontend/` excluding `node_modules`, `dist`, `dist-ssr`, `.env`.
5. Copies `documentation/` verbatim.
6. Copies root `changelog.txt` and `license.txt` if present.
7. Compresses the staging folder to `sonyabus-codecanyon-v1.0.0.zip` via `Compress-Archive`.
8. Deletes the staging directory and prints size + a pre-submission checklist (verify `.env` and `vendor/` are absent, open documentation, upload to CodeCanyon).

Versioning is centralized in this single script — there is no semantic version manifest, git tag convention, or changelog automation beyond the manual `changelog.txt` shipped alongside the ZIP.

## Conventions and constraints observed

- Each component has its own `package.json` and runs Vite independently; there is no monorepo tool (pnpm workspaces, lerna, nx) wiring them together.
- Backend admin assets are split into per-page Vite entries rather than a single bundle, enabling incremental HMR per admin page.
- The frontend dev server proxies both API and storage uploads to the backend, with explicit fallback routes for common Laragon/Apache layouts.
- PHPUnit test environment variables are pinned in `phpunit.xml` (in-memory SQLite, array stores, sync queue) — tests do not require an external database.
- No CI pipeline exists; builds and releases are intended to be run manually on Windows (PowerShell for release ZIP, `.bat` files for quick Vite builds).
- Version number `1.0.0` is hardcoded in `build-release.ps1` and is not synchronized with any package manifest or git tag.