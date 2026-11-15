---
kind: dependency_management
name: Multi-Tool Dependency Management (Composer + npm/Vite per subproject)
category: dependency_management
scope:
    - '**'
source_files:
    - backend/composer.json
    - backend/composer.lock
    - backend/package.json
    - frontend/package.json
    - frontend/package-lock.json
---

## Overview

SonyaBus is a polyglot monorepo with three independent JavaScript/Node projects and one PHP project, each managing its own third-party dependencies through its native package manager. There is no shared workspace tool (no Lerna, Yarn workspaces, Composer `repositories` for private packages, or vendored PHP libraries checked in).

## Systems Used

| Subproject | Manager | Manifest | Lockfile | Notes |
|---|---|---|---|---|
| Backend (Laravel) | Composer (`composer.json`) | `backend/composer.json` | `backend/composer.lock` | PHP runtime + Laravel ecosystem |
| Backend assets (Vite/Tailwind) | npm (`package.json`) | `backend/package.json` | `backend/package-lock.json` | Dev-only build tooling; no runtime JS deps |
| Frontend (React SPA) | npm (`package.json`) | `frontend/package.json` | `frontend/package-lock.json` | React 19 + Vite 8 + ESLint |
| k6 load tests | k6 (Go-based CLI) | none | none | No declared dependencies — relies on k6's built-in modules |

## Key Files

- `backend/composer.json` — declares PHP runtime (`^8.2`), Laravel framework (`^12.0`), Sanctum (`^4.3`), DomPDF (`^3.1`), plus dev tools (PHPUnit, Mockery, Faker, Pail, Collision). Autoloading uses PSR-4 under `App\`, `Database\Factories\`, `Database\Seeders\`, and `Tests\`.
- `backend/composer.lock` — pinned versions of all resolved PHP dependencies.
- `backend/package.json` — only devDependencies: Tailwind CSS v4, Axios, Concurrency runner, Laravel Vite plugin, Vite v7.
- `frontend/package.json` — runtime deps are just `react` and `react-dom` (`^19.2.6`); everything else is devDependency (Vite, ESLint, React types, React hooks plugin).
- `frontend/package-lock.json` — pins exact frontend dependency tree.
- `k6/config/index.js` — k6 has no manifest; configuration is code-only (base URL, credentials, promo codes, thresholds).

## Architecture & Conventions

- **Per-project manifests**: Each subproject owns its own dependency graph. The root directory contains no top-level `composer.json` or `package.json`; there is no cross-project dependency sharing.
- **Lockfiles committed**: Both `backend/composer.lock` and both `package-lock.json` files are present, so CI can install deterministic versions.
- **Stable-only resolution**: `backend/composer.json` sets `minimum-stability: "stable"` and `prefer-stable: true`, preventing accidental installation of beta/dev packages.
- **Plugin allowlist**: Composer config restricts allowed plugins to `php-http/discovery` via `config.allow-plugins`, reducing supply-chain surface from arbitrary Composer scripts.
- **No private registries or vendoring**: No `repositories` entries in `composer.json`, no `.npmrc`/`.yarnrc`, no `GOPRIVATE`, no vendored PHP libraries under `vendor/` (the `backend/vendor/` directory exists but is gitignored — see below). Dependencies are pulled from Packagist and the default npm registry.
- **Build scripts bridge managers**: The Composer `setup` script chains `composer install` → env copy → `artisan key:generate` → `artisan migrate --force` → `npm install` → `npm run build`, coupling PHP and JS installs into one command.
- **Dev server orchestration**: The `dev` Composer script runs four processes concurrently via `concurrently`: Laravel server, queue listener, Pail log tailer, and Vite dev server.

## Conventions & Constraints

- PHP runtime version is constrained to `^8.2` in `backend/composer.json`.
- All PHP packages use caret ranges (`^x.y.z`), not fixed pins, while lockfiles provide exact resolution.
- Frontend runtime dependencies are also caret-ranged (`^19.2.6` for React); devDependencies follow the same pattern.
- Composer autoloading follows PSR-4 conventions for application code and test code.
- k6 tests do not declare external Node/JS dependencies — they rely entirely on k6's standard library and call the app over HTTP.
- The `sonyabus-codecanyon-v1.0.0/` subtree is a frozen distribution copy of the same backend/frontend trees and carries its own copies of `composer.json`, `package.json`, and lockfiles; it is not a separate dependency source.