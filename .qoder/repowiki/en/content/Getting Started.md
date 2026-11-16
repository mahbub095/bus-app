# Getting Started

<cite>
**Referenced Files in This Document**
- [README.md](file://README.md)
- [backend/composer.json](file://backend/composer.json)
- [frontend/package.json](file://frontend/package.json)
- [backend/app/Http/Controllers/Install/InstallController.php](file://backend/app/Http/Controllers/Install/InstallController.php)
- [backend/app/Console/Commands/InstallFinalize.php](file://backend/app/Console/Commands/InstallFinalize.php)
- [backend/routes/web.php](file://backend/routes/web.php)
</cite>

## Update Summary
**Changes Made**
- Removed CodeCanyon-specific installation procedures and Envato license verification steps
- Simplified installation process to focus on core application setup
- Updated environment configuration to remove Envato-specific requirements
- Streamlined troubleshooting section for common installation issues

## Table of Contents
1. [Introduction](#introduction)
2. [Project Structure](#project-structure)
3. [Core Components](#core-components)
4. [Architecture Overview](#architecture-overview)
5. [Detailed Component Analysis](#detailed-component-analysis)
6. [Dependency Analysis](#dependency-analysis)
7. [Performance Considerations](#performance-considerations)
8. [Troubleshooting Guide](#troubleshooting-guide)
9. [Conclusion](#conclusion)

## Introduction
SonyaBus is a full-stack bus ticket booking system with a Laravel 12 backend API and a React 19 customer-facing SPA, plus an admin dashboard for managing routes, schedules, bookings, promotions, reports, and system settings. The system includes interactive seat maps, online payments via ZiniPay, SMS notifications, role-based access control, maintenance mode, and security hardening.

This guide helps you install the backend and frontend, configure environment variables, set up the database, and get the application running quickly without complex licensing procedures.

**Section sources**
- [README.md:1-271](file://README.md#L1-L271)

## Project Structure
The repository contains:
- Backend (Laravel 12): API endpoints, admin web routes, installation wizard, services, models, migrations, seeders, and Blade views.
- Frontend (React 19 + Vite): Customer SPA that calls the backend API.
- Documentation and deployment guides.

```mermaid
graph TB
subgraph "Backend (Laravel 12)"
RWeb["routes/web.php"]
RApi["routes/api.php"]
InstCtrl["InstallController"]
InstCmd["InstallFinalize command"]
DBMig["Database migrations"]
end
subgraph "Frontend (React 19)"
SPA["Customer SPA"]
end
SPA --> RApi
RWeb --> InstCtrl
InstCtrl --> InstCmd
InstCmd --> DBMig
```

**Diagram sources**
- [backend/routes/web.php:27-42](file://backend/routes/web.php#L27-L42)
- [backend/routes/api.php:18-75](file://backend/routes/api.php#L18-L75)
- [backend/app/Http/Controllers/Install/InstallController.php:1-287](file://backend/app/Http/Controllers/Install/InstallController.php#L1-L287)
- [backend/app/Console/Commands/InstallFinalize.php:1-268](file://backend/app/Console/Commands/InstallFinalize.php#L1-L268)

**Section sources**
- [README.md:46-80](file://README.md#L46-L80)

## Core Components
- Installation Wizard: Guides you through database connection, admin account creation, and finalization.
- Database Setup: Runs migrations or imports SQL files during installation.
- Admin and Customer APIs: Provide endpoints for admin operations and customer booking flows.

Key responsibilities:
- InstallController orchestrates the multi-step installation flow.
- InstallFinalize runs background tasks to migrate, create super admin, and lock installer.
- Routes define public API endpoints and admin routes.

**Section sources**
- [backend/app/Http/Controllers/Install/InstallController.php:1-287](file://backend/app/Http/Controllers/Install/InstallController.php#L1-L287)
- [backend/app/Console/Commands/InstallFinalize.php:1-268](file://backend/app/Console/Commands/InstallFinalize.php#L1-L268)
- [backend/routes/web.php:27-42](file://backend/routes/web.php#L27-L42)
- [backend/routes/api.php:18-75](file://backend/routes/api.php#L18-L75)

## Architecture Overview
At a high level:
- The React SPA calls the Laravel API for stations, search, promotions, authentication, bookings, and seat holds.
- The installation wizard is accessible at /install until the system is installed.
- After installation, the system uses Sanctum for authenticated API requests.

```mermaid
sequenceDiagram
participant Browser as "Browser"
participant WebRoutes as "web.php"
participant InstallCtrl as "InstallController"
participant FinalizeCmd as "InstallFinalize"
participant DB as "MySQL"
Browser->>WebRoutes : GET /install
WebRoutes->>InstallCtrl : showLicense()
Browser->>WebRoutes : POST /install/database/save
InstallCtrl->>DB : test connection
Browser->>WebRoutes : POST /install/finalize/run
InstallCtrl->>FinalizeCmd : run in background
FinalizeCmd->>DB : migrate + create tables
FinalizeCmd->>DB : create super admin
FinalizeCmd->>Browser : status polling -> complete
```

**Diagram sources**
- [backend/routes/web.php:27-42](file://backend/routes/web.php#L27-L42)
- [backend/app/Http/Controllers/Install/InstallController.php:46-209](file://backend/app/Http/Controllers/Install/InstallController.php#L46-L209)
- [backend/app/Console/Commands/InstallFinalize.php:51-259](file://backend/app/Console/Commands/InstallFinalize.php#L51-L259)

## Detailed Component Analysis

### System Requirements
- PHP 8.2+
- MySQL 8.0+
- Node.js 18+
- Composer 2.x
- Web server: Nginx or Apache with mod_rewrite
- Required PHP extensions: BCMath, Ctype, cURL, DOM, Fileinfo, JSON, Mbstring, OpenSSL, PCRE, PDO, Tokenizer, XML, GD

These requirements are documented in the project README and enforced by the backend dependencies.

**Section sources**
- [README.md:86-94](file://README.md#L86-L94)
- [backend/composer.json:8-13](file://backend/composer.json#L8-L13)

### Installation Steps

#### Backend Setup
1. Navigate to the backend directory.
2. Install dependencies:
   - composer install --no-dev --optimize-autoloader
3. Copy environment file:
   - cp .env.example .env
4. Generate application key:
   - php artisan key:generate
5. Edit .env with required values:
   - APP_URL
   - APP_FRONTEND_URL
   - DB_* (host, port, database, username, password)
6. Set file permissions:
   - chmod -R 775 storage bootstrap/cache
   - chown -R www-data:www-data storage bootstrap/cache

Notes:
- The backend requires PHP 8.2+ and Laravel 12 dependencies.
- The installation wizard will write some keys to .env during setup.

**Section sources**
- [README.md:104-116](file://README.md#L104-L116)
- [backend/composer.json:8-13](file://backend/composer.json#L8-L13)

#### Frontend Setup
1. Navigate to the frontend directory.
2. Copy environment file:
   - cp .env.example .env
3. Edit .env with required values:
   - VITE_API_BASE_URL
   - VITE_ZINIPAY_API_KEY
4. Install dependencies:
   - npm install
5. Build production assets:
   - npm run build
6. Serve frontend/dist/ via your web server.

Notes:
- The frontend uses React 19 and Vite.
- Ensure Node.js 18+ is installed.

**Section sources**
- [README.md:118-127](file://README.md#L118-L127)
- [frontend/package.json:12-26](file://frontend/package.json#L12-L26)

#### Web Server Configuration
- Point backend domain to backend/public/.
- Point frontend domain to frontend/dist/.
- Enable rewrite rules for Laravel routing.

See the documentation index for full Nginx/Apache configurations.

**Section sources**
- [README.md:129-132](file://README.md#L129-L132)

### Environment Configuration (.env)

Backend .env essentials:
- APP_URL: Base URL of the backend.
- APP_FRONTEND_URL: Base URL of the React SPA.
- DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD: Database credentials.

Frontend .env essentials:
- VITE_API_BASE_URL: Backend API base URL.
- VITE_ZINIPAY_API_KEY: Payment gateway key.

Important:
- Do not commit secrets; use .env.example as a template.
- The installation wizard may update certain keys like APP_INSTALLED.

**Section sources**
- [README.md:112-113](file://README.md#L112-L113)
- [README.md:122-123](file://README.md#L122-L123)

### Initial Database Setup
During installation:
- You can provide MySQL credentials directly in the wizard.
- Optionally upload a SQL file to pre-seed the database before migrations.
- If no SQL file is provided, migrations run automatically during finalization.

Database schema highlights:
- Core tables include users, buses, routes, schedules, bookings, promotions, stations, and site settings.

```mermaid
erDiagram
USERS {
bigint id PK
string name
string email
string password
string role
timestamp created_at
timestamp updated_at
}
BUSES {
bigint id PK
string name
string number_plate
int seats
timestamp created_at
timestamp updated_at
}
ROUTES {
bigint id PK
string from_station
string to_station
decimal distance
decimal fare
timestamp created_at
timestamp updated_at
}
SCHEDULES {
bigint id PK
bigint bus_id FK
bigint route_id FK
datetime departure_time
datetime arrival_time
int available_seats
timestamp created_at
timestamp updated_at
}
BOOKINGS {
bigint id PK
bigint user_id FK
bigint schedule_id FK
string passenger_name
string passenger_email
json seats
decimal total_amount
string status
timestamp created_at
timestamp updated_at
}
```

**Diagram sources**
- [backend/database/migrations/0001_01_01_000000_create_users_table.php:1-30](file://backend/database/migrations/0001_01_01_000000_create_users_table.php#L1-L30)
- [backend/database/migrations/2026_06_01_000020_create_buses_table.php:1-30](file://backend/database/migrations/2026_06_01_000020_create_buses_table.php#L1-L30)
- [backend/database/migrations/2026_06_01_000030_create_routes_table.php:1-30](file://backend/database/migrations/2026_06_01_000030_create_routes_table.php#L1-L30)
- [backend/database/migrations/2026_06_01_000040_create_schedules_table.php:1-30](file://backend/database/migrations/2026_06_01_000040_create_schedules_table.php#L1-L30)
- [backend/database/migrations/2026_06_01_000050_create_bookings_table.php:1-30](file://backend/database/migrations/2026_06_01_000050_create_bookings_table.php#L1-L30)

**Section sources**
- [backend/app/Http/Controllers/Install/InstallController.php:90-144](file://backend/app/Http/Controllers/Install/InstallController.php#L90-L144)
- [backend/app/Console/Commands/InstallFinalize.php:66-167](file://backend/app/Console/Commands/InstallFinalize.php#L66-L167)

### Quick Start Commands
Backend:
- cd backend
- composer install --no-dev --optimize-autoloader
- cp .env.example .env
- php artisan key:generate
- Edit .env (APP_URL, APP_FRONTEND_URL, DB_*)
- chmod -R 775 storage bootstrap/cache
- chown -R www-data:www-data storage bootstrap/cache

Frontend:
- cd frontend
- cp .env.example .env
- Edit .env (VITE_API_BASE_URL, VITE_ZINIPAY_API_KEY)
- npm install
- npm run build

Serve:
- Point backend domain to backend/public/
- Point frontend domain to frontend/dist/

**Section sources**
- [README.md:104-132](file://README.md#L104-L132)

## Dependency Analysis
- Backend depends on Laravel 12, Sanctum, DomPDF, and other packages defined in composer.json.
- Frontend depends on React 19 and Vite as defined in package.json.
- Installation flow depends on InstallController and InstallFinalize command.
- Routes define public API endpoints and admin routes.

```mermaid
graph LR
Composer["composer.json"] --> Laravel["Laravel 12"]
Composer --> Sanctum["Sanctum"]
Composer --> DomPDF["DomPDF"]
PackageJSON["package.json"] --> React["React 19"]
PackageJSON --> Vite["Vite"]
WebRoutes["web.php"] --> InstallCtrl["InstallController"]
ApiRoutes["api.php"] --> CustomerAPI["Customer API"]
InstallCtrl --> FinalizeCmd["InstallFinalize"]
```

**Diagram sources**
- [backend/composer.json:8-13](file://backend/composer.json#L8-L13)
- [frontend/package.json:12-26](file://frontend/package.json#L12-L26)
- [backend/routes/web.php:27-42](file://backend/routes/web.php#L27-L42)
- [backend/routes/api.php:18-75](file://backend/routes/api.php#L18-L75)
- [backend/app/Http/Controllers/Install/InstallController.php:1-287](file://backend/app/Http/Controllers/Install/InstallController.php#L1-L287)
- [backend/app/Console/Commands/InstallFinalize.php:1-268](file://backend/app/Console/Commands/InstallFinalize.php#L1-L268)

**Section sources**
- [backend/composer.json:8-13](file://backend/composer.json#L8-L13)
- [frontend/package.json:12-26](file://frontend/package.json#L12-L26)
- [backend/routes/web.php:27-42](file://backend/routes/web.php#L27-L42)
- [backend/routes/api.php:18-75](file://backend/routes/api.php#L18-L75)

## Performance Considerations
- Use production builds for the frontend (npm run build) and serve static assets via your web server.
- Ensure proper PHP extensions are enabled for optimal performance.
- Keep cache directories writable and owned by the web server user.
- Avoid running development commands in production.

[No sources needed since this section provides general guidance]

## Troubleshooting Guide

Common issues and resolutions:
- Installer remains accessible after installation:
  - The installer locks itself when APP_INSTALLED=true and the installed marker file exists.
  - Clear caches and ensure storage/framework/installed exists.
- Database connection errors:
  - Double-check DB_* values in .env.
  - Test connectivity manually and ensure the MySQL user has privileges.
- Frontend cannot reach the API:
  - Verify VITE_API_BASE_URL points to the correct backend URL.
  - Ensure CORS and maintenance mode allow requests.
- Permission errors:
  - Ensure storage and bootstrap/cache are writable by the web server user.
  - Set ownership to www-data:www-data where applicable.
- Migration failures:
  - Check database permissions and ensure all required tables can be created.
  - Review migration logs for specific error details.

Relevant implementation details:
- InstallController validates inputs, tests DB connections via raw PDO, writes credentials to .env, and triggers background finalization.
- InstallFinalize manages migrations, super admin creation, and installer locking.

**Section sources**
- [backend/app/Http/Controllers/Install/InstallController.php:90-144](file://backend/app/Http/Controllers/Install/InstallController.php#L90-L144)
- [backend/app/Console/Commands/InstallFinalize.php:66-228](file://backend/app/Console/Commands/InstallFinalize.php#L66-L228)

## Conclusion
You now have the essential steps to install and configure SonyaBus, including backend and frontend setup, environment configuration, and initial database setup. The simplified installation process focuses on core application setup without complex licensing procedures. For detailed server configurations and advanced topics, consult the documentation index and the specific guides referenced throughout this document.

[No sources needed since this section summarizes without analyzing specific files]