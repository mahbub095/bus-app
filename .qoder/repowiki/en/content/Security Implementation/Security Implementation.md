# Security Implementation

<cite>
**Referenced Files in This Document**
- [sanctum.php](file://backend/config/sanctum.php)
- [auth.php](file://backend/config/auth.php)
- [session.php](file://backend/config/session.php)
- [cors.php](file://backend/config/cors.php)
- [api.php](file://backend/routes/api.php)
- [web.php](file://backend/routes/web.php)
- [UserAuthController.php](file://backend/app/Http/Controllers/API/UserAuthController.php)
- [AuthController.php](file://backend/app/Http/Controllers/Admin/AuthController.php)
- [User.php](file://backend/app/Models/User.php)
- [EnsureUserIsAdmin.php](file://backend/app/Http/Middleware/EnsureUserIsAdmin.php)
- [EnsureUserIsSuperAdmin.php](file://backend/app/Http/Middleware/EnsureUserIsSuperAdmin.php)
- [EnsureUserHasMenuPermission.php](file://backend/app/Http/Middleware/EnsureUserHasMenuPermission.php)
- [LicenseService.php](file://backend/app\Services\LicenseService.php)
- [License.php](file://backend/app\Models\License.php)
- [EnsureLicenseIsActivated.php](file://backend/app\Http\Middleware\EnsureLicenseIsActivated.php)
</cite>

## Table of Contents
1. Introduction
2. Project Structure
3. Core Components
4. Architecture Overview
5. Detailed Component Analysis
6. Dependency Analysis
7. Performance Considerations
8. Troubleshooting Guide
9. Conclusion

## Introduction
This document explains the security implementation for SonyaBus, focusing on authentication and authorization with Laravel Sanctum, role-based access control, permission management, input validation, CSRF protection, XSS prevention, SQL injection mitigation, rate limiting, file upload security, path traversal prevention, secure environment variable handling, license verification security, session management, and hardening best practices. It maps these controls to concrete files in the backend so you can trace behavior directly in the codebase.

## Project Structure
The security-relevant parts of the backend are organized around:
- Configuration for authentication, sessions, CORS, and Sanctum
- API routes for public and authenticated endpoints
- Admin web routes protected by session-based auth and middleware
- Middleware enforcing admin, super-admin, menu permissions, and license activation
- Models for users and licenses
- Services for license verification

```mermaid
graph TB
subgraph "Configuration"
C1["config/sanctum.php"]
C2["config/auth.php"]
C3["config/session.php"]
C4["config/cors.php"]
end
subgraph "Routing"
R1["routes/api.php"]
R2["routes/web.php"]
end
subgraph "Controllers"
K1["API/UserAuthController.php"]
K2["Admin/AuthController.php"]
end
subgraph "Middleware"
M1["EnsureUserIsAdmin.php"]
M2["EnsureUserIsSuperAdmin.php"]
M3["EnsureUserHasMenuPermission.php"]
M4["EnsureLicenseIsActivated.php"]
end
subgraph "Models & Services"
U1["Models/User.php"]
L1["Models/License.php"]
S1["Services/LicenseService.php"]
end
R1 --> K1
R2 --> K2
R1 --> M4
R2 --> M1
R2 --> M2
R2 --> M3
K1 --> U1
K2 --> U1
M4 --> S1
S1 --> L1
C1 --> R1
C2 --> K2
C3 --> K2
C4 --> R1
```

**Diagram sources**
- [sanctum.php:1-88](file://backend/config/sanctum.php#L1-L88)
- [auth.php:1-118](file://backend/config/auth.php#L1-L118)
- [session.php:1-218](file://backend/config/session.php#L1-L218)
- [cors.php:1-35](file://backend/config/cors.php#L1-L35)
- [api.php:1-77](file://backend/routes/api.php#L1-L77)
- [web.php:1-293](file://backend/routes/web.php#L1-L293)
- [UserAuthController.php:1-220](file://backend/app/Http/Controllers/API/UserAuthController.php#L1-L220)
- [AuthController.php:1-83](file://backend/app/Http/Controllers/Admin/AuthController.php#L1-L83)
- [User.php:1-76](file://backend/app/Models/User.php#L1-L76)
- [EnsureUserIsAdmin.php:1-41](file://backend/app/Http/Middleware/EnsureUserIsAdmin.php#L1-L41)
- [EnsureUserIsSuperAdmin.php:1-38](file://backend/app/Http/Middleware/EnsureUserIsSuperAdmin.php#L1-L38)
- [EnsureUserHasMenuPermission.php:1-41](file://backend/app/Http/Middleware/EnsureUserHasMenuPermission.php#L1-L41)
- [LicenseService.php:1-408](file://backend/app\Services\LicenseService.php#L1-L408)
- [License.php:1-126](file://backend/app\Models\License.php#L1-L126)
- [EnsureLicenseIsActivated.php:1-169](file://backend/app\Http\Middleware\EnsureLicenseIsActivated.php#L1-L169)

**Section sources**
- [sanctum.php:1-88](file://backend/config/sanctum.php#L1-L88)
- [auth.php:1-118](file://backend/config/auth.php#L1-L118)
- [session.php:1-218](file://backend/config/session.php#L1-L218)
- [cors.php:1-35](file://backend/config/cors.php#L1-L35)
- [api.php:1-77](file://backend/routes/api.php#L1-L77)
- [web.php:1-293](file://backend/routes/web.php#L1-L293)

## Core Components
- Authentication
  - Customer API uses Laravel Sanctum tokens via routes grouped under auth:sanctum.
  - Admin dashboard uses session-based authentication with guards configured in auth.php.
- Authorization
  - Role checks: admin vs super_admin via User model methods.
  - Menu-level permissions enforced through EnsureUserHasMenuPermission middleware.
- License gating
  - Two-stage gate: installation lock and license activation check via EnsureLicenseIsActivated middleware and LicenseService.

Key responsibilities:
- Sanctum configuration for stateful domains, token prefix, and middleware integration.
- Session configuration for lifetime, encryption, cookie flags, and SameSite policy.
- CORS configuration for cross-origin requests between frontend and API.
- Route-level rate limiting for authentication endpoints.
- Input validation in controllers using Laravel’s validator rules.
- CSRF protection for session-based admin routes.
- Secure password hashing and reset flows.
- Domain-limited license verification against Envato APIs.

**Section sources**
- [sanctum.php:1-88](file://backend/config/sanctum.php#L1-L88)
- [auth.php:1-118](file://backend/config/auth.php#L1-L118)
- [session.php:1-218](file://backend/config/session.php#L1-L218)
- [cors.php:1-35](file://backend/config/cors.php#L1-L35)
- [api.php:1-77](file://backend/routes/api.php#L1-L77)
- [web.php:1-293](file://backend/routes/web.php#L1-L293)
- [UserAuthController.php:1-220](file://backend/app/Http/Controllers/API/UserAuthController.php#L1-L220)
- [AuthController.php:1-83](file://backend/app/Http/Controllers/Admin/AuthController.php#L1-L83)
- [User.php:1-76](file://backend/app/Models/User.php#L1-L76)
- [EnsureUserIsAdmin.php:1-41](file://backend/app/Http/Middleware/EnsureUserIsAdmin.php#L1-L41)
- [EnsureUserIsSuperAdmin.php:1-38](file://backend/app/Http/Middleware/EnsureUserIsSuperAdmin.php#L1-L38)
- [EnsureUserHasMenuPermission.php:1-41](file://backend/app/Http/Middleware/EnsureUserHasMenuPermission.php#L1-L41)
- [LicenseService.php:1-408](file://backend/app\Services\LicenseService.php#L1-L408)
- [License.php:1-126](file://backend/app\Models\License.php#L1-L126)
- [EnsureLicenseIsActivated.php:1-169](file://backend/app\Http\Middleware\EnsureLicenseIsActivated.php#L1-L169)

## Architecture Overview
The system enforces security at multiple layers:
- Routing layer applies middleware for maintenance mode, license gating, and role/permission checks.
- Controllers validate inputs and perform business logic securely.
- Models encapsulate domain rules (roles, permissions, license domain limits).
- Services handle external integrations (Envato license verification) with retries, timeouts, and safe caching.

```mermaid
sequenceDiagram
participant Client as "Client"
participant Routes as "Routes"
participant LicenseGate as "EnsureLicenseIsActivated"
participant Auth as "Sanctum / Session"
participant RBAC as "Role/Permission Middleware"
participant Controller as "Controller"
participant Service as "LicenseService"
participant DB as "Database"
Client->>Routes : HTTP Request
Routes->>LicenseGate : Check install + license
LicenseGate->>Service : isActivated()
Service->>DB : Query license status
DB-->>Service : Activation flag
Service-->>LicenseGate : Activated?
LicenseGate-->>Routes : Allow or block
alt Allowed
Routes->>Auth : Authenticate (Sanctum or Session)
Auth-->>Routes : User context
Routes->>RBAC : Enforce roles/permissions
RBAC-->>Routes : Access granted/denied
Routes->>Controller : Handle request
Controller-->>Client : Response
else Blocked
LicenseGate-->>Client : 403 or redirect
end
```

**Diagram sources**
- [api.php:1-77](file://backend/routes/api.php#L1-L77)
- [web.php:1-293](file://backend/routes/web.php#L1-L293)
- [EnsureLicenseIsActivated.php:1-169](file://backend/app\Http\Middleware\EnsureLicenseIsActivated.php#L1-L169)
- [LicenseService.php:1-408](file://backend/app\Services\LicenseService.php#L1-L408)
- [License.php:1-126](file://backend/app\Models\License.php#L1-L126)
- [UserAuthController.php:1-220](file://backend/app/Http/Controllers/API/UserAuthController.php#L1-L220)
- [AuthController.php:1-83](file://backend/app/Http/Controllers/Admin/AuthController.php#L1-L83)

## Detailed Component Analysis

### Authentication and Authorization

#### Sanctum API Authentication Flow
- Public registration/login endpoints are rate-limited and validated.
- On success, a Sanctum personal access token is issued; logout deletes the current token.
- Authenticated customer routes require auth:sanctum middleware.

```mermaid
sequenceDiagram
participant FE as "Frontend"
participant API as "API Routes"
participant Ctrl as "UserAuthController"
participant Model as "User"
participant Sanctum as "Sanctum"
FE->>API : POST /api/auth/register
API->>Ctrl : register()
Ctrl->>Model : create user
Model-->>Ctrl : User instance
Ctrl->>Sanctum : createToken('frontend-api')
Sanctum-->>Ctrl : Token
Ctrl-->>FE : {token, user}
FE->>API : POST /api/auth/login
API->>Ctrl : login()
Ctrl->>Model : find by email
Model-->>Ctrl : User
Ctrl->>Sanctum : delete old tokens + create new token
Sanctum-->>Ctrl : Token
Ctrl-->>FE : {token, user}
FE->>API : GET /api/auth/me
API->>Ctrl : me()
Ctrl-->>FE : {user}
```

**Diagram sources**
- [api.php:41-75](file://backend/routes/api.php#L41-L75)
- [UserAuthController.php:17-79](file://backend/app/Http/Controllers/API/UserAuthController.php#L17-L79)
- [User.php:1-76](file://backend/app/Models/User.php#L1-L76)

**Section sources**
- [api.php:1-77](file://backend/routes/api.php#L1-L77)
- [UserAuthController.php:17-79](file://backend/app/Http/Controllers/API/UserAuthController.php#L17-L79)
- [User.php:1-76](file://backend/app/Models/User.php#L1-L76)

#### Admin Session-Based Authentication
- Admin login uses session guard with CSRF protection.
- Successful login regenerates session and redirects to admin dashboard.
- Logout invalidates session and regenerates token.

```mermaid
flowchart TD
Start(["Admin Login"]) --> Validate["Validate credentials"]
Validate --> Attempt{"Auth::attempt()"}
Attempt --> |Success| CheckRole{"isAdmin()?"}
CheckRole --> |Yes| Regenerate["Regenerate session"]
Regenerate --> Redirect["Redirect to /admin"]
CheckRole --> |No| Logout["Logout + invalidate session"]
Logout --> Error["Return error message"]
Attempt --> |Failure| Error
```

**Diagram sources**
- [web.php:68-70](file://backend/routes/web.php#L68-L70)
- [AuthController.php:24-61](file://backend/app/Http/Controllers/Admin/AuthController.php#L24-L61)
- [User.php:44-52](file://backend/app/Models/User.php#L44-L52)

**Section sources**
- [web.php:68-70](file://backend/routes/web.php#L68-L70)
- [AuthController.php:24-61](file://backend/app/Http/Controllers/Admin/AuthController.php#L24-L61)
- [User.php:44-52](file://backend/app/Models/User.php#L44-L52)

#### Role-Based Access Control and Permissions
- Roles: user, admin, super_admin.
- Super admin bypasses menu permissions; admins must have explicit menu permissions.
- Middleware enforce:
  - EnsureUserIsAdmin: requires admin role.
  - EnsureUserIsSuperAdmin: requires super_admin role.
  - EnsureUserHasMenuPermission: checks per-menu permission array.

```mermaid
classDiagram
class User {
+string role
+bool isAdmin()
+bool isSuperAdmin()
+bool hasMenuPermission(menu)
}
class EnsureUserIsAdmin {
+handle(request, next)
}
class EnsureUserIsSuperAdmin {
+handle(request, next)
}
class EnsureUserHasMenuPermission {
+handle(request, next, menu)
}
EnsureUserIsAdmin --> User : "checks isAdmin()"
EnsureUserIsSuperAdmin --> User : "checks isSuperAdmin()"
EnsureUserHasMenuPermission --> User : "checks hasMenuPermission()"
```

**Diagram sources**
- [User.php:44-74](file://backend/app/Models/User.php#L44-L74)
- [EnsureUserIsAdmin.php:12-38](file://backend/app/Http/Middleware/EnsureUserIsAdmin.php#L12-L38)
- [EnsureUserIsSuperAdmin.php:12-35](file://backend/app/Http/Middleware/EnsureUserIsSuperAdmin.php#L12-L35)
- [EnsureUserHasMenuPermission.php:15-38](file://backend/app/Http/Middleware/EnsureUserHasMenuPermission.php#L15-L38)

**Section sources**
- [User.php:44-74](file://backend/app/Models/User.php#L44-L74)
- [EnsureUserIsAdmin.php:12-38](file://backend/app/Http/Middleware/EnsureUserIsAdmin.php#L12-L38)
- [EnsureUserIsSuperAdmin.php:12-35](file://backend/app/Http/Middleware/EnsureUserIsSuperAdmin.php#L12-L35)
- [EnsureUserHasMenuPermission.php:15-38](file://backend/app/Http/Middleware/EnsureUserHasMenuPermission.php#L15-L38)

### Input Validation, CSRF, XSS, and SQL Injection Mitigation

- Input validation
  - Controllers use Laravel’s request validation rules for all sensitive endpoints (register, login, password updates, forgot/reset password).
  - Passwords are hashed automatically via model casts.
- CSRF protection
  - Admin routes are session-based and protected by Laravel’s default CSRF middleware.
  - Sanctum config includes ValidateCsrfToken middleware for stateful SPA flows.
- XSS prevention
  - Blade templates render content safely by default.
  - Recommended: add security headers middleware to set X-XSS-Protection and CSP.
- SQL injection mitigation
  - Eloquent ORM and parameterized queries prevent SQL injection.
  - Avoid raw SQL where possible; if used, bind parameters.

**Section sources**
- [UserAuthController.php:17-208](file://backend/app/Http/Controllers/API/UserAuthController.php#L17-L208)
- [AuthController.php:24-81](file://backend/app/Http/Controllers/Admin/AuthController.php#L24-L81)
- [sanctum.php:81-85](file://backend/config/sanctum.php#L81-L85)

### Rate Limiting
- Authentication endpoints are rate-limited:
  - API auth group: throttle:10,1.
  - Admin login route: throttle:10,1.
- Additional sensitive endpoints (e.g., password update) should be individually throttled.

```mermaid
flowchart TD
A["Request to /api/auth/*"] --> B["Throttle Middleware"]
B --> C{"Within limit?"}
C --> |Yes| D["Proceed to controller"]
C --> |No| E["429 Too Many Requests"]
```

**Diagram sources**
- [api.php:41-46](file://backend/routes/api.php#L41-L46)
- [web.php:68-70](file://backend/routes/web.php#L68-L70)

**Section sources**
- [api.php:41-46](file://backend/routes/api.php#L41-L46)
- [web.php:68-70](file://backend/routes/web.php#L68-L70)

### File Upload Security and Path Traversal Prevention
- Admin site settings include logo/favicon upload routes.
- Recommendations:
  - Validate MIME type and extension server-side.
  - Store uploads outside public directory when possible; serve via storage disk.
  - Sanitize filenames and disallow path traversal characters.
  - Restrict allowed file types and sizes.
  - Set restrictive filesystem permissions.

[No sources needed since this section provides general guidance]

### Secure Environment Variable Handling
- Sanctum stateful domains configurable via SANCTUM_STATEFUL_DOMAINS.
- Session encryption and lifetime controlled via SESSION_ENCRYPT, SESSION_LIFETIME, SESSION_EXPIRE_ON_CLOSE.
- CORS origins and credentials controlled via cors.php.
- Best practices:
  - Never commit .env to version control.
  - Use environment-specific values for production.
  - Rotate APP_KEY regularly.
  - Restrict access to .env and storage directories.

**Section sources**
- [sanctum.php:21-26](file://backend/config/sanctum.php#L21-L26)
- [session.php:35-50](file://backend/config/session.php#L35-L50)
- [cors.php:18-33](file://backend/config/cors.php#L18-L33)

### License Verification Security
- LicenseService enforces strict rules:
  - UUID format validation.
  - Local dev bypass only when explicitly enabled.
  - Seller bypass code restricted to exact constant.
  - Envato API calls with timeout and retry.
  - Product ID match to ensure purchase belongs to SonyaBus.
  - Domain limit enforcement (max 5 unique domains).
- EnsureLicenseIsActivated middleware gates application access:
  - Stage 1: Installation lock.
  - Stage 2: License activation required for non-public routes.

```mermaid
flowchart TD
Start(["verify(purchaseCode, persistDb, domain)"]) --> Format["Validate UUID format"]
Format --> LocalBypass{"Local bypass enabled?"}
LocalBypass --> |Yes| DomainCheck["checkDomainLimit()"]
LocalBypass --> |No| SellerBypass{"Seller bypass code?"}
SellerBypass --> |Yes| DomainCheck
SellerBypass --> |No| ApiCall["Call Envato API"]
ApiCall --> Status{"Status ok?"}
Status --> |No| Fail["Return fail message"]
Status --> |Yes| ProductCheck{"Product ID matches?"}
ProductCheck --> |No| Fail
ProductCheck --> |Yes| DomainCheck
DomainCheck --> Result["buildResult() -> save/cache/env"]
```

**Diagram sources**
- [LicenseService.php:56-157](file://backend/app\Services\LicenseService.php#L56-L157)
- [LicenseService.php:263-297](file://backend/app\Services\LicenseService.php#L263-L297)
- [LicenseService.php:303-328](file://backend/app\Services\LicenseService.php#L303-L328)
- [LicenseService.php:363-379](file://backend/app\Services\LicenseService.php#L363-L379)
- [License.php:17-126](file://backend/app\Models\License.php#L17-L126)

**Section sources**
- [LicenseService.php:56-157](file://backend/app\Services\LicenseService.php#L56-L157)
- [LicenseService.php:263-297](file://backend/app\Services\LicenseService.php#L263-L297)
- [LicenseService.php:303-328](file://backend/app\Services\LicenseService.php#L303-L328)
- [LicenseService.php:363-379](file://backend/app\Services\LicenseService.php#L363-L379)
- [License.php:17-126](file://backend/app\Models\License.php#L17-L126)
- [EnsureLicenseIsActivated.php:26-169](file://backend/app\Http\Middleware\EnsureLicenseIsActivated.php#L26-L169)

### Session Management
- Session driver defaults to database.
- Lifetime, expiration on close, encryption, httpOnly, SameSite, and partitioned cookies are configurable.
- Admin login/logout performs session regeneration and invalidation for security.

```mermaid
stateDiagram-v2
[*] --> Unauthenticated
Unauthenticated --> Authenticated : "Login successful"
Authenticated --> Idle : "Session active"
Idle --> Authenticated : "Renewal"
Authenticated --> Unauthenticated : "Logout + invalidate"
```

**Diagram sources**
- [session.php:21-50](file://backend/config/session.php#L21-L50)
- [session.php:172-202](file://backend/config/session.php#L172-L202)
- [AuthController.php:54-61](file://backend/app/Http/Controllers/Admin/AuthController.php#L54-L61)

**Section sources**
- [session.php:21-50](file://backend/config/session.php#L21-L50)
- [session.php:172-202](file://backend/config/session.php#L172-L202)
- [AuthController.php:54-61](file://backend/app/Http/Controllers/Admin/AuthController.php#L54-L61)

### Security Hardening Best Practices
- CORS
  - Restrict allowed_origins to known frontend domains.
  - Enable supports_credentials only when necessary and pair with proper Sanctum stateful domains.
- Sanctum
  - Set token expiration to a reasonable value.
  - Use token_prefix to aid secret scanning.
- Headers
  - Add security headers middleware to set X-Content-Type-Options, X-Frame-Options, Referrer-Policy, CSP, and HSTS in production.
- Password Policy
  - Enforce minimum length and complexity.
- Logging
  - Avoid logging sensitive data (passwords, reset codes).
  - Implement audit logs for critical actions.

**Section sources**
- [cors.php:18-33](file://backend/config/cors.php#L18-L33)
- [sanctum.php:47-68](file://backend/config/sanctum.php#L47-L68)
- [UserAuthController.php:127-163](file://backend/app/Http/Controllers/API/UserAuthController.php#L127-L163)

## Dependency Analysis
Security components interact as follows:
- Routes apply middleware for license gating, maintenance mode, and role/permission checks.
- Controllers depend on models for user and license operations.
- LicenseService depends on License model and external Envato APIs.
- Session and Sanctum configurations influence how authentication state is maintained.

```mermaid
graph LR
R_API["routes/api.php"] --> MW_LIC["EnsureLicenseIsActivated"]
R_WEB["routes/web.php"] --> MW_ADMIN["EnsureUserIsAdmin"]
R_WEB --> MW_SUPER["EnsureUserIsSuperAdmin"]
R_WEB --> MW_MENU["EnsureUserHasMenuPermission"]
MW_LIC --> SVC_LIC["LicenseService"]
SVC_LIC --> MOD_LIC["License"]
CTRL_AUTH["UserAuthController"] --> MOD_USER["User"]
CTRL_ADMIN["Admin/AuthController"] --> MOD_USER
```

**Diagram sources**
- [api.php:1-77](file://backend/routes/api.php#L1-L77)
- [web.php:1-293](file://backend/routes/web.php#L1-L293)
- [EnsureLicenseIsActivated.php:1-169](file://backend/app\Http\Middleware\EnsureLicenseIsActivated.php#L1-L169)
- [LicenseService.php:1-408](file://backend/app\Services\LicenseService.php#L1-L408)
- [License.php:1-126](file://backend/app\Models\License.php#L1-L126)
- [UserAuthController.php:1-220](file://backend/app/Http/Controllers/API/UserAuthController.php#L1-L220)
- [AuthController.php:1-83](file://backend/app/Http/Controllers/Admin/AuthController.php#L1-L83)

**Section sources**
- [api.php:1-77](file://backend/routes/api.php#L1-L77)
- [web.php:1-293](file://backend/routes/web.php#L1-L293)
- [EnsureLicenseIsActivated.php:1-169](file://backend/app\Http\Middleware\EnsureLicenseIsActivated.php#L1-L169)
- [LicenseService.php:1-408](file://backend/app\Services\LicenseService.php#L1-L408)
- [License.php:1-126](file://backend/app\Models\License.php#L1-L126)
- [UserAuthController.php:1-220](file://backend/app/Http/Controllers/API/UserAuthController.php#L1-L220)
- [AuthController.php:1-83](file://backend/app/Http/Controllers/Admin/AuthController.php#L1-L83)

## Performance Considerations
- Rate limiting protects authentication endpoints from brute-force attacks while maintaining responsiveness.
- LicenseService caches activation status to reduce repeated DB lookups.
- External API calls in LicenseService use timeouts and retries to avoid hanging requests.
- Session driver choice impacts performance; database driver is default but Redis may improve throughput.

[No sources needed since this section provides general guidance]

## Troubleshooting Guide
- 401/403 on API calls
  - Verify Sanctum stateful domains and CORS configuration.
  - Ensure tokens are present and not expired.
- Admin login loops or redirects
  - Confirm session configuration and that CSRF tokens are included.
  - Check role checks in Admin/AuthController and EnsureUserIsAdmin middleware.
- License activation failures
  - Inspect LicenseService logs for API errors.
  - Verify product ID and domain limits.
  - Check EnsureLicenseIsActivated middleware allowlists.

**Section sources**
- [sanctum.php:21-26](file://backend/config/sanctum.php#L21-L26)
- [cors.php:18-33](file://backend/config/cors.php#L18-L33)
- [UserAuthController.php:17-79](file://backend/app/Http/Controllers/API/UserAuthController.php#L17-L79)
- [AuthController.php:24-61](file://backend/app/Http/Controllers/Admin/AuthController.php#L24-L61)
- [EnsureUserIsAdmin.php:12-38](file://backend/app/Http/Middleware/EnsureUserIsAdmin.php#L12-L38)
- [LicenseService.php:106-157](file://backend/app\Services\LicenseService.php#L106-L157)
- [EnsureLicenseIsActivated.php:98-169](file://backend/app\Http\Middleware\EnsureLicenseIsActivated.php#L98-L169)

## Conclusion
SonyaBus implements a layered security model combining Sanctum-based API authentication, session-based admin authentication, role and permission middleware, robust input validation, CSRF protection, and comprehensive license gating. Configuration files centralize security-critical settings such as CORS, Sanctum, and sessions. The license verification service adds strict validation, domain limits, and safe external API interactions. Following the recommended hardening steps—restrictive CORS, token expiration, security headers, strong password policies, and careful environment management—will further strengthen the application’s security posture.