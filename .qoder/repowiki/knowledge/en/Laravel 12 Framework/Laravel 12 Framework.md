---
kind: external_dependency
name: Laravel 12 Framework
slug: laravel
category: external_dependency
category_hints:
    - vendor_identity
scope:
    - '**'
---

### Laravel
- Backend is a Laravel 12 project (PHP 8.2+) using Sanctum for API token auth, database queue driver, and Blade views for the admin dashboard and install wizard.
- The framework ships with Sanctum (`config/sanctum.php`, personal access tokens migration) — API clients authenticate via Sanctum tokens, not sessions.