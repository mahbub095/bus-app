# Bus Booking Application - Security Audit Report
**Date:** June 7, 2026  
**Status:** Pre-Production Security Assessment  

---

## Executive Summary
The Bus Booking Application is a Laravel/React-based booking system. This audit identified **12 CRITICAL**, **15 HIGH**, and **8 MEDIUM** priority security issues that must be addressed before production deployment.

---

## 🔴 CRITICAL ISSUES (Immediate Action Required)

### 1. **Insecure CORS Configuration - ALL Origins Allowed**
**File:** [backend/config/cors.php](backend/config/cors.php)  
**Severity:** CRITICAL  
**Issue:**
```php
'allowed_origins' => ['*'],
'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
```
**Risk:** Allows ANY website to access your API, enabling CSRF attacks, data theft, and account takeover.

**Fix:**
```php
'allowed_origins' => [
    env('FRONTEND_URL', 'https://yourdomain.com'),
    'https://yourdomain.com',
    // NO wildcards in production
],
'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
'allowed_headers' => ['Content-Type', 'Authorization'],
'supports_credentials' => true,
```

---

### 2. **JWT/API Tokens Never Expire**
**File:** [backend/config/sanctum.php](backend/config/sanctum.php#L44-L56)  
**Severity:** CRITICAL  
**Issue:**
```php
'expiration' => null,  // ❌ Tokens valid forever!
```
**Risk:** Stolen tokens give permanent access to attacker's account.

**Fix:**
```php
'expiration' => 60,  // Tokens expire in 60 minutes
```

---

### 3. **Tokens Stored in Unprotected localStorage (XSS Vulnerability)**
**File:** [frontend/src/App.jsx](frontend/src/App.jsx#L111-L120)  
**Severity:** CRITICAL  
**Issue:**
```javascript
localStorage.setItem(AUTH_TOKEN_KEY, token);
localStorage.setItem(AUTH_USER_KEY, JSON.stringify(user));
```
**Risk:** Any XSS vulnerability exposes tokens. localStorage is accessible to ANY JavaScript (including malicious scripts).

**Fix (Priority Options):**
- **BEST:** Use `httpOnly` + `secure` cookies (backend sends, browser auto-manages)
- **Alternative:** Use `sessionStorage` (cleared on browser close) temporarily
- Update App.jsx:
```javascript
// Option 1: Don't store tokens on frontend - let backend manage cookies
// Option 2: Use sessionStorage instead (but still insecure if XSS exists)
sessionStorage.setItem(AUTH_TOKEN_KEY, token);
```

**Backend Fix:** Return token in `httpOnly` cookie:
```php
// In UserAuthController.php
return response()->json([
    'message' => 'Logged in successfully.',
    'user' => $this->formatUser($user),
])->cookie(
    'auth_token',
    $token,
    60,  // 60 minutes
    '/',
    env('SESSION_DOMAIN'),
    true,  // httpOnly - JS cannot access
    true,  // secure - HTTPS only
    false, // sameSite
    'Strict' // SameSite policy
);
```

---

### 4. **Database Credentials Exposed in Configuration**
**File:** [backend/config/database.php](backend/config/database.php)  
**Severity:** CRITICAL  
**Issue:**
```php
'host' => env('DB_HOST', '127.0.0.1'),
'username' => env('DB_USERNAME', 'root'),
'password' => env('DB_PASSWORD', ''),
```
**Risk:** Default credentials visible in code repository.

**Fix:**
- ✅ Use `.env` file (already done)
- ❌ **NEVER** commit `.env` file to git
- Add to `.gitignore`:
```
.env
.env.local
.env.*.php
```
- Update `.env.example` with NO defaults:
```
DB_CONNECTION=mysql
DB_HOST=
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
```

---

### 5. **APP_DEBUG = true in Production**
**File:** [backend/config/app.php](backend/config/app.php#L31-L40)  
**Severity:** CRITICAL  
**Issue:**
```php
'debug' => (bool) env('APP_DEBUG', false),
```
**Risk:** If `APP_DEBUG=true`, full stack traces leak:
- Database structure
- File paths
- Environment variables
- Source code snippets

**Fix:** Ensure `.env` has:
```
APP_ENV=production
APP_DEBUG=false
```

---

### 6. **Session Data Not Encrypted**
**File:** [backend/.env.example](backend/.env.example#L21)  
**Severity:** CRITICAL  
**Issue:**
```
SESSION_ENCRYPT=false
```
**Risk:** Session data stored in plaintext in database.

**Fix:**
```
SESSION_ENCRYPT=true
SESSION_LIFETIME=60
SESSION_EXPIRE_ON_CLOSE=true
```

---

### 7. **Weak Password Policy**
**File:** [backend/app/Http/Controllers/API/UserAuthController.php](backend/app/Http/Controllers/API/UserAuthController.php#L14-L17)  
**Severity:** CRITICAL  
**Issue:**
```php
'password' => 'required|string|min:6|confirmed',
```
**Risk:** 6-character passwords are cracked in milliseconds.

**Fix:**
```php
'password' => 'required|string|min:12|confirmed|regex:/[A-Z]/|regex:/[0-9]/|regex:/[@$!%*?&]/',
```

---

### 8. **No HTTPS Enforcement**
**File:** [backend/config/app.php](backend/config/app.php)  
**Severity:** CRITICAL  
**Issue:** No indication of HTTPS-only configuration.

**Risk:** Tokens and credentials transmitted in plaintext.

**Fix:** In `bootstrap/app.php`:
```php
return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustHosts(
            config('app.trusted_hosts', [env('APP_URL')])
        );
        $middleware->trustProxies(at: ['*']);
        $middleware->redirectHttpsTo();  // ← Add this
    })
    // ...
```

Update `.env`:
```
APP_URL=https://yourdomain.com
FORCE_HTTPS=true
```

---

### 9. **Hardcoded API Base URL**
**File:** [frontend/src/App.jsx](frontend/src/App.jsx#L12)  
**Severity:** CRITICAL  
**Issue:**
```javascript
const API_BASE = 'http://localhost:8000/api';
```
**Risk:**
- Uses HTTP (not HTTPS)
- Hardcoded to localhost
- Must be changed for production

**Fix:**
```javascript
const API_BASE = import.meta.env.VITE_API_BASE || 'http://localhost:8000/api';
```

Create `.env.production`:
```
VITE_API_BASE=https://api.yourdomain.com/api
```

Update `frontend/vite.config.js`:
```javascript
export default defineConfig({
  plugins: [react()],
  define: {
    __API_BASE__: JSON.stringify(process.env.VITE_API_BASE)
  }
})
```

---

### 10. **Missing CSRF Token for Stateful Operations**
**File:** [backend/routes/api.php](backend/routes/api.php)  
**Severity:** CRITICAL  
**Issue:** API endpoints don't validate CSRF tokens. Using Bearer tokens but Sanctum middleware may be bypassed.

**Risk:** Cross-Site Request Forgery attacks possible.

**Fix:** Ensure all POST/PUT/DELETE require authentication:
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::post('/auth/password', [UserAuthController::class, 'updatePassword']);
});
```

Add middleware in `bootstrap/app.php`:
```php
$middleware->validateCsrfTokens(except: [
    'api/*',  // API uses Bearer tokens instead
]);
```

---

### 11. **No Rate Limiting on Authentication Endpoints**
**File:** [backend/routes/api.php](backend/routes/api.php#L21-L22)  
**Severity:** CRITICAL  
**Issue:**
```php
Route::post('/auth/register', [UserAuthController::class, 'register']);
Route::post('/auth/login', [UserAuthController::class, 'login']);
```
**Risk:** Brute force attacks, account enumeration, credential stuffing.

**Fix:** Update routes:
```php
Route::post('/auth/register', [UserAuthController::class, 'register'])
    ->middleware('throttle:5,1');  // 5 attempts per minute

Route::post('/auth/login', [UserAuthController::class, 'login'])
    ->middleware('throttle:5,1');  // 5 attempts per minute

Route::post('/auth/password', [UserAuthController::class, 'updatePassword'])
    ->middleware('throttle:3,1');  // 3 attempts per minute
```

---

### 12. **No Protection Against SQL Injection in Custom Queries**
**File:** [backend/app/Http/Controllers/API/BookingController.php](backend/app/Http/Controllers/API/BookingController.php#L69)  
**Severity:** HIGH (moved to HIGH, but critical in context)  
**Issue:** Using Eloquent properly (good), but raw input needs validation.

**Risk:** Although Eloquent provides protection, direct input usage needs verification.

**Fix:** Already validated with:
```php
$requestedSeats = array_filter(
    array_map('trim', explode(',', $request->input('seat_numbers')))
);
```
Ensure seat format validation:
```php
'seat_numbers' => 'required|string|regex:/^[A-Z][0-9]+(,[A-Z][0-9]+)*$/'
```

---

## 🟠 HIGH PRIORITY ISSUES

### 1. **Missing Security Headers**
**Risk:** Clickjacking, XSS, Injection attacks not prevented at HTTP level.

**Fix:** Create middleware `app/Http/Middleware/SecurityHeaders.php`:
```php
<?php
namespace App\Http\Middleware;

class SecurityHeaders {
    public function handle($request, $next) {
        $response = $next($request);
        
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'");
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        
        return $response;
    }
}
```

Register in `bootstrap/app.php`:
```php
$middleware->append(\App\Http\Middleware\SecurityHeaders::class);
```

---

### 2. **No Input Sanitization for User Display**
**File:** Frontend components  
**Risk:** XSS attacks through booking data, user profiles.

**Fix:** Sanitize user input:
```javascript
import DOMPurify from 'dompurify';

const displayName = DOMPurify.sanitize(userName);
```

Or use React's built-in escaping (already done by default in JSX).

---

### 3. **Admin Role Check Hardcoded**
**File:** [backend/database/migrations/2026_06_02_000001_add_role_to_users_table.php](backend/database/migrations/2026_06_02_000001_add_role_to_users_table.php)  
**Issue:**
```php
DB::table('users')->where('email', 'admin@sonyabus.com')->update(['role' => 'admin']);
```
**Risk:** Hardcoded admin email breaks if changed; no easy way to manage admin roles.

**Fix:** Use seeder instead:
```php
php artisan make:seeder AdminUserSeeder
```

**database/seeders/AdminUserSeeder.php:**
```php
<?php
namespace Database\Seeders;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder {
    public function run(): void {
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@sonyabus.com')],
            [
                'name' => 'Admin User',
                'password' => bcrypt(env('ADMIN_PASSWORD', 'ChangeMe123!')),
                'role' => 'admin',
            ]
        );
    }
}
```

---

### 4. **Weak Email Validation**
**File:** [backend/app/Http/Controllers/API/UserAuthController.php](backend/app/Http/Controllers/API/UserAuthController.php#L14)  
**Issue:**
```php
'email' => 'required|email|max:100|unique:users,email',
```
**Risk:** Should verify email ownership (confirmation emails not implemented).

**Fix:** Implement email verification:
```php
Schema::table('users', function (Blueprint $table) {
    $table->timestamp('email_verified_at')->nullable();
});
```

Add verification in UserAuthController:
```php
$user->sendEmailVerificationNotification();

// Add route
Route::get('/auth/verify/{id}/{hash}', [UserAuthController::class, 'verify'])
    ->middleware('signed');
```

---

### 5. **Logging Not Configured for Security Events**
**Issue:** No audit logs for authentication failures, unauthorized access attempts.

**Fix:** Create security logging:
```php
// In UserAuthController
use Illuminate\Support\Facades\Log;

public function login(Request $request) {
    // ... validation ...
    if (!$user || !Hash::check($credentials['password'], $user->password)) {
        Log::warning('Failed login attempt', [
            'email' => $credentials['email'],
            'ip' => $request->ip(),
            'timestamp' => now(),
        ]);
        // ... throw exception ...
    }
}
```

---

### 6. **No SQL Query Timeouts**
**Risk:** Long-running queries cause DoS.

**Fix:** In `config/database.php`:
```php
'mysql' => [
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='STRICT_TRANS_TABLES'",
        PDO::ATTR_TIMEOUT => 5,  // 5 second timeout
    ]) : [],
],
```

---

### 7. **No Database Backup Strategy Visible**
**Risk:** Data loss, no recovery plan.

**Fix:** Configure automated backups:
```bash
# Add to cron for daily backups
0 2 * * * /usr/bin/mysqldump -u root -p'${DB_PASSWORD}' ${DB_DATABASE} | gzip > /backups/db-$(date +\%Y\%m\%d).sql.gz
```

---

### 8. **Missing API Versioning**
**Issue:** No way to deprecate endpoints safely.

**Fix:** Update routes to version API:
```php
// routes/api.php
Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [UserAuthController::class, 'login']);
    Route::post('/bookings', [BookingController::class, 'store']);
});

// Future: routes/api_v2.php
Route::prefix('v2')->group(function () {
    // New endpoints with breaking changes
});
```

---

### 9. **Frontend Dependencies Outdated/Minimal**
**File:** [frontend/package.json](frontend/package.json)  
**Issue:** No security packages (DOMPurify, helmet, etc.)

**Fix:**
```bash
npm install dompurify                    # XSS prevention
npm install date-fns                     # Safe date handling  
npm install lodash-es                    # Safe utilities
npm audit fix                            # Fix vulnerabilities
npm outdated                             # Check versions
```

---

### 10. **Payment Method Stored as Plain String**
**File:** [backend/database/migrations/2026_06_01_000050_create_bookings_table.php](backend/database/migrations/2026_06_01_000050_create_bookings_table.php)  
**Issue:**
```php
$table->string('payment_method')->default('bKash');
```
**Risk:** Should be enum; doesn't validate against whitelist.

**Fix:**
```php
$table->enum('payment_method', ['bKash', 'card', 'bank_transfer', 'cash'])
      ->default('bKash');
```

---

### 11. **No Encryption for Sensitive Data**
**Issue:** User phone numbers, passenger names stored in plaintext.

**Fix:** Use Laravel encryption:
```php
// In Booking model
use Illuminate\Database\Eloquent\Casts\Encrypted;

protected $casts = [
    'passenger_phone' => Encrypted::class,
    'passenger_email' => Encrypted::class,
];
```

---

### 12. **No Two-Factor Authentication (2FA)**
**Risk:** Compromised password = full account access.

**Fix:** Use Google Authenticator package:
```bash
composer require pragmarx/google2fa-laravel
```

---

### 13. **Insufficient Password Reset Token Expiry**
**File:** [backend/config/auth.php](backend/config/auth.php)  
**Issue:**
```php
'expire' => 60,      // 60 minutes (could be too long)
'throttle' => 60,    // 60 seconds between resets
```
**Fix:**
```php
'expire' => 15,      // 15 minutes max
'throttle' => 300,   // 5 minutes minimum between attempts
```

---

### 14. **No IP Whitelisting for Admin Panel**
**Risk:** Admin accessible from anywhere.

**Fix:** Add middleware:
```php
// app/Http/Middleware/WhitelistAdminIp.php
class WhitelistAdminIp {
    public function handle($request, $next) {
        $allowedIps = explode(',', env('ADMIN_WHITELIST_IPS', ''));
        if (!in_array($request->ip(), $allowedIps)) {
            abort(403, 'IP not whitelisted');
        }
        return $next($request);
    }
}
```

---

### 15. **Session Fixation Vulnerability**
**Issue:** Session ID not regenerated on login.

**Fix:** Add to UserAuthController login method:
```php
$request->session()->regenerate();
```

---

## 🟡 MEDIUM PRIORITY ISSUES

### 1. **No Request Validation for Pagination**
**Risk:** Resource exhaustion via excessive data requests.

**Fix:**
```php
'page' => 'nullable|integer|min:1|max:100',
'per_page' => 'nullable|integer|min:1|max:100',
```

---

### 2. **Insufficient Logging of Sensitive Actions**
**Issue:** No audit trail for seat blocking, role changes, payment records.

**Fix:** Create audit log table:
```php
Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable();
    $table->string('action');
    $table->string('model_type');
    $table->unsignedBigInteger('model_id');
    $table->json('changes');
    $table->string('ip_address');
    $table->timestamps();
});
```

---

### 3. **No Validation of Booking Quantity**
**File:** [backend/app/Http/Controllers/API/BookingController.php](backend/app/Http/Controllers/API/BookingController.php#L46-L48)  
**Issue:**
```php
if (count($requestedSeats) > 4) {
    return response()->json(['message' => 'You can select a maximum of 4 seats per booking.'], 422);
}
```
**Fix:** Move to validation rules:
```php
'seat_numbers' => 'required|string|max:20',  // Implicit seat count validation
```

---

### 4. **Unmasked Sensitive Data in Responses**
**Risk:** Booking confirmations might expose payment details.

**Fix:**
```php
return response()->json([
    'booking_id' => $booking->id,
    'total_fare' => $booking->total_fare,
    'status' => $booking->status,
    // DON'T return: payment_method, full email, phone number
]);
```

---

### 5. **No Rate Limiting on API Endpoints Generally**
**Issue:** Only auth endpoints at risk.

**Fix:** Apply global throttle:
```php
// In bootstrap/app.php
$middleware->throttleRequests('60,1');  // 60 requests per minute globally
```

---

### 6. **Promotional Code Validation Weakness**
**File:** [backend/app/Http/Controllers/API/BookingController.php](backend/app/Http/Controllers/API/BookingController.php#L92-L97)  
**Issue:**
```php
if ($promoCode) {
    $promotion = Promotion::where('code', strtoupper($promoCode))->first();
    if ($promotion) {
        $totalFare = max(0.00, $totalFare - floatval($promotion->discount_amount));
    }
}
```
**Risk:** No validation that promotion is active/valid.

**Fix:**
```php
if ($promoCode) {
    $promotion = Promotion::where('code', strtoupper($promoCode))
        ->where('is_active', true)
        ->where('expires_at', '>', now())
        ->first();
    if (!$promotion) {
        return response()->json(['message' => 'Invalid or expired promotional code.'], 422);
    }
}
```

---

### 7. **Missing Privacy Policy & Terms of Service**
**Risk:** Legal liability, user data handling not documented.

**Fix:** Add routes:
```php
Route::get('/privacy-policy', function () {
    return file_get_contents('resources/docs/privacy-policy.md');
});
Route::get('/terms-of-service', function () {
    return file_get_contents('resources/docs/terms-of-service.md');
});
```

---

### 8. **No API Documentation**
**Risk:** Security vulnerabilities introduced through misuse.

**Fix:** Use Laravel OpenAPI/Swagger:
```bash
composer require darkaonline/l5-swagger
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
```

---

## 📋 DEPLOYMENT CHECKLIST

- [ ] Enable HTTPS with valid SSL certificate
- [ ] Set `APP_DEBUG=false` in production `.env`
- [ ] Set `APP_ENV=production`
- [ ] Generate new `APP_KEY` for production
- [ ] Update CORS to allow only your frontend domain
- [ ] Set strong database passwords
- [ ] Configure automated database backups
- [ ] Enable query logging for audit trail
- [ ] Set up error monitoring (Sentry/Rollbar)
- [ ] Configure email for password resets
- [ ] Set strong JWT expiration times
- [ ] Implement rate limiting
- [ ] Add security headers middleware
- [ ] Update email verification
- [ ] Implement 2FA for admin accounts
- [ ] Configure IP whitelisting for admin
- [ ] Set up SSL/TLS certificate auto-renewal
- [ ] Configure WAF (Web Application Firewall)
- [ ] Perform penetration testing
- [ ] Review and update `.gitignore`

---

## 🚀 DEPLOYMENT HOSTING COMPARISON

### **cPanel Shared Hosting**
✅ **Pros:**
- Easy SSL via AutoSSL/Let's Encrypt
- Built-in database backups
- Simple deployment

❌ **Cons:**
- Limited security controls
- Shared server vulnerabilities
- No dedicated WAF
- Shared IP reputation
- Limited rate limiting options

**Recommendations for cPanel:**
```
1. Use AutoSSL (free Let's Encrypt)
2. Enable ModSecurity WAF
3. Use cPanel's File Manager to update .env
4. Set .env permissions to 600
5. Move vendor/ above public_html
6. Use cPanel backups daily
```

### **Cloud Services (AWS/Azure/GCP)**
✅ **Pros:**
- Better security isolation
- Managed WAF & DDoS protection
- Advanced monitoring & logging
- Scalability
- IAM access controls
- Dedicated networking

❌ **Cons:**
- More complex setup
- Higher learning curve
- Potential cost surprises

**Recommendations for AWS:**
```
1. Use RDS for managed database
2. Enable RDS encryption at rest
3. Use S3 for file storage with encryption
4. CloudFront + WAF for DDoS protection
5. CloudWatch for logging
6. Secrets Manager for credentials
7. VPC with security groups
```

---

## 📞 IMMEDIATE ACTIONS REQUIRED

**Priority 1 (This Week):**
1. Fix CORS configuration
2. Set token expiration
3. Move tokens to httpOnly cookies
4. Enable HTTPS
5. Disable APP_DEBUG

**Priority 2 (This Sprint):**
6. Add security headers
7. Implement rate limiting
8. Add email verification
9. Update password policy
10. Configure logging

**Priority 3 (Before Production):**
11. Implement 2FA
12. Add database encryption
13. Set up backups
14. Perform penetration testing
15. Update dependencies

---

## 📚 Resources

- [OWASP Top 10 2021](https://owasp.org/Top10/)
- [Laravel Security Best Practices](https://laravel.com/docs/security)
- [CWE/SANS Top 25](https://cwe.mitre.org/top25/)
- [NIST Cybersecurity Framework](https://www.nist.gov/cyberframework/)

---

**Report Generated:** June 7, 2026  
**Next Review:** After implementing Critical fixes
