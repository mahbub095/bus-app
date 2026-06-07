# Quick Security Fixes - Copy & Paste Ready

## 1️⃣ Fix CORS (5 minutes)

**File:** `backend/config/cors.php`

```php
<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:3000'),
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'Accept',
        'X-Requested-With',
    ],
    'exposed_headers' => [],
    'max_age' => 86400,
    'supports_credentials' => true,
];
```

---

## 2️⃣ Fix Sanctum Token Expiration (2 minutes)

**File:** `backend/config/sanctum.php` - Line 44

```php
'expiration' => 60,  // ← Change from null to 60
```

---

## 3️⃣ Fix .env for Production (5 minutes)

**Create:** `.env` (copy from `.env.example`)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
FRONTEND_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=your-host
DB_PORT=3306
DB_DATABASE=bus_booking
DB_USERNAME=your_user
DB_PASSWORD=your_password

SESSION_ENCRYPT=true
SESSION_LIFETIME=60

SANCTUM_STATEFUL_DOMAINS=yourdomain.com,api.yourdomain.com
SANCTUM_TOKEN_PREFIX=api_

ADMIN_EMAIL=admin@yourdomain.com
ADMIN_PASSWORD=ChangeMeToStrongPassword123!
```

Then run:
```bash
php artisan key:generate
```

---

## 4️⃣ Add Security Headers Middleware (10 minutes)

**Create:** `app/Http/Middleware/SecurityHeaders.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy', 
            "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'"
        );

        if (env('APP_ENV') === 'production') {
            $response->headers->set('Strict-Transport-Security', 
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        return $response;
    }
}
```

**Register in:** `bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    
    $middleware->alias([
        'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
    ]);
})
```

---

## 5️⃣ Add Rate Limiting (5 minutes)

**File:** `backend/routes/api.php`

```php
<?php

use App\Http\Controllers\API\BookingController;
use App\Http\Controllers\API\PromotionController;
use App\Http\Controllers\API\SearchController;
use App\Http\Controllers\API\StationController;
use App\Http\Controllers\API\UserAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/stations', [StationController::class, 'index']);
Route::get('/search', [SearchController::class, 'search']);
Route::get('/promotions', [PromotionController::class, 'index']);
Route::get('/promotions/check', [PromotionController::class, 'check']);

// Auth with rate limiting
Route::post('/auth/register', [UserAuthController::class, 'register'])
    ->middleware('throttle:5,1');

Route::post('/auth/login', [UserAuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [UserAuthController::class, 'logout']);
    Route::get('/auth/me', [UserAuthController::class, 'me']);
    Route::post('/auth/password', [UserAuthController::class, 'updatePassword'])
        ->middleware('throttle:3,1');

    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/mine', [BookingController::class, 'mine']);
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);
});
```

---

## 6️⃣ Fix Password Validation (3 minutes)

**File:** `backend/app/Http/Controllers/API/UserAuthController.php`

Replace in `register()` method:
```php
// FROM:
'password' => 'required|string|min:6|confirmed',

// TO:
'password' => 'required|string|min:12|confirmed|regex:/[A-Z]/|regex:/[0-9]/|regex:/[@$!%*?&]/',
```

This requires:
- 12+ characters
- At least 1 uppercase letter
- At least 1 number
- At least 1 special character

---

## 7️⃣ Fix Frontend to Use Cookies (15 minutes)

**File:** `frontend/src/App.jsx`

**REMOVE these lines:**
```javascript
const AUTH_TOKEN_KEY = 'sonyabus_auth_token';
const AUTH_USER_KEY = 'sonyabus_auth_user';
```

**REPLACE authHeaders function:**
```javascript
// FROM:
const authHeaders = (extra = {}) => {
    const headers = { 'Accept': 'application/json', ...extra };
    if (authToken) {
        headers['Authorization'] = `Bearer ${authToken}`;
    }
    return headers;
};

// TO:
const authHeaders = (extra = {}) => {
    return {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        ...extra
    };
};
```

**REPLACE persistAuth function:**
```javascript
// FROM:
const persistAuth = (user, token) => {
    setAuthUser(user);
    setAuthToken(token);
    localStorage.setItem(AUTH_USER_KEY, JSON.stringify(user));
    localStorage.setItem(AUTH_TOKEN_KEY, token);
    // ...
};

// TO:
const persistAuth = (user, token) => {
    setAuthUser(user);
    // Cookie is auto-managed by browser
    // No need to store token manually
};
```

**REPLACE fetch calls - add credentials:**
```javascript
// Example: login
const res = await fetch(`${API_BASE}${endpoint}`, {
    method: 'POST',
    credentials: 'include',  // ← ADD THIS
    headers: authHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify(body)
});

// Example: get bookings
const res = await fetch(`${API_BASE}/bookings/mine`, {
    credentials: 'include',  // ← ADD THIS
    headers: authHeaders()
});
```

**REPLACE clearAuth function:**
```javascript
// FROM:
const clearAuth = () => {
    setAuthUser(null);
    setAuthToken(null);
    setCancelBookings([]);
    localStorage.removeItem(AUTH_USER_KEY);
    localStorage.removeItem(AUTH_TOKEN_KEY);
};

// TO:
const clearAuth = () => {
    setAuthUser(null);
    setCancelBookings([]);
    // Cookie deleted by backend on logout
};
```

**REMOVE useEffect that restores from localStorage:**
```javascript
// DELETE THIS ENTIRE BLOCK:
useEffect(() => {
    const savedToken = localStorage.getItem(AUTH_TOKEN_KEY);
    const savedUser = localStorage.getItem(AUTH_USER_KEY);
    if (savedToken && savedUser) {
        setAuthToken(savedToken);
        try {
            setAuthUser(JSON.parse(savedUser));
        } catch (err) {
            clearAuth();
        }
    }
}, []);
```

---

## 8️⃣ Update Backend to Send httpOnly Cookies (10 minutes)

**File:** `backend/app/Http/Controllers/API/UserAuthController.php`

**REPLACE register() return statement:**
```php
// FROM:
return response()->json([
    'message' => 'Account created successfully.',
    'token' => $token,
    'user' => $this->formatUser($user),
], 201);

// TO:
return response()->json([
    'message' => 'Account created successfully.',
    'user' => $this->formatUser($user),
], 201)
->cookie(
    'auth_token',
    $token,
    60,  // expires in 60 minutes
    '/',
    env('SESSION_DOMAIN'),
    env('APP_ENV') === 'production',  // secure flag for HTTPS
    true,  // httpOnly - JavaScript cannot access
    false,
    'Strict'  // SameSite
);
```

**REPLACE login() return statement:**
```php
// FROM:
return response()->json([
    'message' => 'Logged in successfully.',
    'token' => $token,
    'user' => $this->formatUser($user),
]);

// TO:
return response()->json([
    'message' => 'Logged in successfully.',
    'user' => $this->formatUser($user),
])
->cookie(
    'auth_token',
    $token,
    60,
    '/',
    env('SESSION_DOMAIN'),
    env('APP_ENV') === 'production',
    true,
    false,
    'Strict'
);
```

**ADD to logout() method:**
```php
return response()->json([
    'message' => 'Logged out successfully.',
])->cookie('auth_token', '', -1);  // ← Delete cookie
```

---

## 9️⃣ Enable Session Encryption (2 minutes)

**File:** `backend/config/session.php` - Line 17

```php
// FROM:
'encrypt' => env('SESSION_ENCRYPT', false),

// TO:
'encrypt' => env('SESSION_ENCRYPT', true),
```

**File:** `.env`
```env
SESSION_ENCRYPT=true
SESSION_LIFETIME=60
SESSION_EXPIRE_ON_CLOSE=true
```

---

## 🔟 Add Promotional Code Validation (5 minutes)

**File:** `backend/app/Http/Controllers/API/BookingController.php`

Find the promo code validation section around line 92:

```php
// FROM:
if ($promoCode) {
    $promotion = Promotion::where('code', strtoupper($promoCode))->first();
    if ($promotion) {
        $totalFare = max(0.00, $totalFare - floatval($promotion->discount_amount));
    }
}

// TO:
if ($promoCode) {
    $promotion = Promotion::where('code', strtoupper($promoCode))
        ->where('is_active', true)
        ->where('expires_at', '>', now())
        ->first();
    
    if (!$promotion) {
        return response()->json([
            'message' => 'Invalid or expired promotional code.',
        ], 422);
    }
    
    $totalFare = max(0.00, $totalFare - floatval($promotion->discount_amount));
}
```

---

## 1️⃣1️⃣ Add Seat Validation (3 minutes)

**File:** `backend/app/Http/Controllers/API/BookingController.php`

Replace the validation in `store()` method:

```php
// FROM:
$request->validate([
    'schedule_id' => 'required|exists:schedules,id',
    'passenger_name' => 'required|string|max:100',
    'passenger_phone' => 'required|string|max:20',
    'passenger_email' => 'required|email|max:100',
    'seat_numbers' => 'required|string',
    // ...
]);

// TO:
$request->validate([
    'schedule_id' => 'required|exists:schedules,id',
    'passenger_name' => 'required|string|max:100',
    'passenger_phone' => 'required|string|regex:/^[0-9+\-\s()]+$/',
    'passenger_email' => 'required|email|max:100',
    'seat_numbers' => 'required|string|regex:/^[A-Z]\d+(,[A-Z]\d+)*$/',  // ← Added validation
    'payment_method' => 'required|in:bKash,card,bank_transfer,cash',  // ← Whitelist
    // ...
]);
```

---

## 1️⃣2️⃣ Quick .gitignore Fix (2 minutes)

**File:** `.gitignore` (update existing)

Add or ensure these exist:
```
.env
.env.local
.env.production
.env.*.php
.env*.local

storage/logs/*
!storage/logs/.gitkeep

bootstrap/cache/*
!bootstrap/cache/.gitkeep

node_modules/
vendor/

.DS_Store
.idea/
.vscode/*
!.vscode/extensions.json

*.log
*.sql
backup.sql
```

---

## Deployment Steps (In Order)

### Step 1: Prepare Files
```bash
# 1. Backup current database
mysqldump -u root -p database_name > backup_$(date +%Y%m%d).sql

# 2. Copy .env.example to .env
cp .env.example .env

# 3. Update .env with production values (DB, URLs, etc.)
nano .env

# 4. Generate app key
php artisan key:generate

# 5. Install dependencies
composer install --optimize-autoloader --no-dev

# 6. Run migrations
php artisan migrate --force

# 7. Clear cache
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 2: Set Permissions (Linux/Mac)
```bash
chmod 644 .env
chmod 755 bootstrap/cache
chmod 755 storage
chmod -R 755 storage/app
chmod -R 755 storage/framework
chmod -R 755 storage/logs
```

### Step 3: Enable HTTPS
```bash
# For cPanel: Use AutoSSL
# For Linux: Use Certbot
sudo certbot certonly --standalone -d yourdomain.com
```

### Step 4: Verify Configuration
```bash
php artisan config:show | grep -E "(APP_ENV|APP_DEBUG|APP_URL)"
# Should show:
# APP_ENV => production
# APP_DEBUG => false
# APP_URL => https://yourdomain.com
```

### Step 5: Test Endpoints
```bash
# Test auth endpoints
curl -X POST https://yourdomain.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'

# Should return token in httpOnly cookie
```

---

## Critical Commands Before Production

```bash
# 1. Check for security vulnerabilities
composer audit

# 2. Run tests
php artisan test

# 3. Check for debug statements
grep -r "dd\|var_dump" app/ --include="*.php"

# 4. Verify no secrets in code
grep -r "password\|secret\|key" .env.example --include="*.php"

# 5. Check file permissions
ls -la storage bootstrap/cache

# 6. Verify SSL certificate
openssl s_client -connect yourdomain.com:443

# 7. Test CORS settings
curl -H "Origin: https://yourdomain.com" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Content-Type" \
  -X OPTIONS https://yourdomain.com/api/auth/login -v
```

---

**After deploying, test these security features:**

- [ ] HTTPS redirects HTTP
- [ ] httpOnly cookies work (no JS access)
- [ ] Rate limiting blocks excess requests
- [ ] Security headers present in response
- [ ] CORS only allows your domain
- [ ] Sessions are encrypted
- [ ] Passwords meet 12-char minimum
- [ ] Token expires in 60 minutes
- [ ] APP_DEBUG is false
- [ ] Error messages don't leak info

