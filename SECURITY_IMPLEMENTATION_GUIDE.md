# Security Implementation Guide - Bus Booking App

## Quick Start - Critical Fixes (1-2 hours)

### Fix 1: Update CORS Configuration

**File:** `backend/config/cors.php`

```php
<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:3000'),
        env('APP_URL', 'http://localhost:8000'),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'Accept',
        'X-Requested-With',
    ],

    'exposed_headers' => [
        'X-Total-Count',
        'X-Page-Number',
    ],

    'max_age' => 86400,  // Cache CORS for 24 hours

    'supports_credentials' => true,  // Allow cookies in CORS requests
];
```

---

### Fix 2: Set Token Expiration

**File:** `backend/config/sanctum.php`

Change:
```php
'expiration' => null,  // ❌ Bad
```

To:
```php
'expiration' => 60,  // ✅ 60 minutes
```

---

### Fix 3: Use httpOnly Cookies Instead of localStorage

**Backend:** `backend/app/Http/Controllers/API/UserAuthController.php`

```php
<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserAuthController extends BaseController
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:users,email',
            'password' => 'required|string|min:12|confirmed|regex:/[A-Z]/|regex:/[0-9]/|regex:/[@$!%*?&]/',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'user',
        ]);

        $token = $user->createToken('frontend-api')->plainTextToken;

        return response()->json([
            'message' => 'Account created successfully.',
            'user' => $this->formatUser($user),
        ], 201)
        ->cookie(
            'auth_token',
            $token,
            60,  // 60 minutes
            '/',
            env('SESSION_DOMAIN'),
            env('APP_ENV') === 'production',  // HTTPS only in production
            true,  // httpOnly - JavaScript cannot access
            false, // sameSite will be set separately
            'Strict' // SameSite strict
        );
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->isUser()) {
            return response()->json([
                'message' => 'Admin accounts must sign in through the admin portal.',
            ], 403);
        }

        // Delete old tokens
        $user->tokens()->delete();
        $token = $user->createToken('frontend-api')->plainTextToken;

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
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ])->cookie('auth_token', '', -1);  // Delete cookie
    }

    protected function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }
}
```

**Frontend:** `frontend/src/App.jsx`

```javascript
// Remove localStorage token handling
// Remove this:
// const AUTH_TOKEN_KEY = 'sonyabus_auth_token';
// const AUTH_USER_KEY = 'sonyabus_auth_user';

// Update authHeaders to use cookies automatically
const authHeaders = (extra = {}) => {
    return {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        credentials: 'include',  // ← Include cookies
        ...extra
    };
};

// Remove localStorage usage
// Replace:
// localStorage.setItem(AUTH_TOKEN_KEY, token);
// With cookies (automatic via fetch with credentials: 'include')

// Update fetch calls
const handleAuthSubmit = async (e) => {
    e.preventDefault();
    setIsAuthLoading(true);

    const endpoint = authMode === 'register' ? '/auth/register' : '/auth/login';
    const body = authMode === 'register'
        ? authForm
        : { email: authForm.email, password: authForm.password };

    try {
        const res = await fetch(`${API_BASE}${endpoint}`, {
            method: 'POST',
            credentials: 'include',  // ← Include cookies
            headers: authHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify(body)
        });
        const data = await res.json();

        if (res.ok) {
            setAuthUser(data.user);
            // Cookie is automatically managed by browser
            showToast(data.message || 'Welcome!', 'success');
            // ... rest of logic
        }
    } catch (err) {
        showToast('Network error during authentication.', 'error');
    } finally {
        setIsAuthLoading(false);
    }
};
```

---

### Fix 4: Environment Configuration

**File:** `.env.example`

```env
# === APPLICATION ===
APP_NAME="Bus Booking System"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:TBD_GENERATE_WITH_ARTISAN
APP_URL=https://yourdomain.com
FRONTEND_URL=https://frontend.yourdomain.com

# === SECURITY ===
BCRYPT_ROUNDS=12
SESSION_ENCRYPT=true
SESSION_LIFETIME=60
SESSION_EXPIRE_ON_CLOSE=true
SANCTUM_STATEFUL_DOMAINS=yourdomain.com,api.yourdomain.com

# === DATABASE ===
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=bus_booking
DB_USERNAME=
DB_PASSWORD=

# === CACHE & SESSION ===
CACHE_STORE=redis
SESSION_DRIVER=database
QUEUE_CONNECTION=database

# === MAIL ===
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

# === ADMIN ===
ADMIN_EMAIL=admin@yourdomain.com
ADMIN_PASSWORD=
ADMIN_WHITELIST_IPS=YOUR_IP

# === AWS (if using S3) ===
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=

# === REDIS ===
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# === LOGGING ===
LOG_CHANNEL=stack
LOG_LEVEL=warning
```

Then run:
```bash
cp .env.example .env
php artisan key:generate
```

---

### Fix 5: Add Rate Limiting

**File:** `backend/routes/api.php`

```php
<?php

use App\Http\Controllers\API\BookingController;
use App\Http\Controllers\API\PromotionController;
use App\Http\Controllers\API\SearchController;
use App\Http\Controllers\API\StationController;
use App\Http\Controllers\API\UserAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public customer API (React frontend — /api prefix)
|--------------------------------------------------------------------------
*/

Route::get('/stations', [StationController::class, 'index']);
Route::get('/search', [SearchController::class, 'search']);
Route::get('/promotions', [PromotionController::class, 'index']);
Route::get('/promotions/check', [PromotionController::class, 'check']);

// Auth endpoints with rate limiting
Route::post('/auth/register', [UserAuthController::class, 'register'])
    ->middleware('throttle:5,1');  // 5 attempts per minute

Route::post('/auth/login', [UserAuthController::class, 'login'])
    ->middleware('throttle:5,1');  // 5 attempts per minute

/*
|--------------------------------------------------------------------------
| Authenticated customer API (Laravel Sanctum)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [UserAuthController::class, 'logout']);
    Route::get('/auth/me', [UserAuthController::class, 'me']);
    
    Route::post('/auth/password', [UserAuthController::class, 'updatePassword'])
        ->middleware('throttle:3,1');  // 3 attempts per minute

    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/mine', [BookingController::class, 'mine']);
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);
});
```

---

### Fix 6: Add Security Headers

**File:** `app/Http/Middleware/SecurityHeaders.php` (create new file)

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

        // Prevent MIME-type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Clickjacking protection
        $response->headers->set('X-Frame-Options', 'DENY');

        // XSS protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Content Security Policy
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline'; " .
               "style-src 'self' 'unsafe-inline'; " .
               "img-src 'self' data: https:; " .
               "font-src 'self'; " .
               "connect-src 'self' " . env('APP_URL', 'http://localhost') . "; " .
               "frame-ancestors 'none'; " .
               "base-uri 'self'; " .
               "form-action 'self'";

        $response->headers->set('Content-Security-Policy', $csp);

        // HSTS for HTTPS
        if (env('APP_ENV') === 'production') {
            $response->headers->set(
                'Strict-Transport-Security',
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

### Fix 7: Session Encryption

**File:** `config/session.php`

Update:
```php
'encrypt' => env('SESSION_ENCRYPT', true),  // Change to true
```

**File:** `.env`

```env
SESSION_ENCRYPT=true
SESSION_LIFETIME=60
SESSION_EXPIRE_ON_CLOSE=true
```

---

### Fix 8: Update Password Policy

Already shown in UserAuthController above. Min 12 chars with uppercase, number, special char.

---

## Intermediate Fixes (4-8 hours)

### Fix 9: Email Verification

**Migration:** Create new file `database/migrations/2026_06_07_add_email_verification.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });
    }
};
```

**Model:** Update `app/Models/User.php`

```php
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    // ... existing code ...
}
```

**Controller:** Update `app/Http/Controllers/API/UserAuthController.php`

```php
public function register(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:100',
        'email' => 'required|email|max:100|unique:users,email',
        'password' => 'required|string|min:12|confirmed|regex:/[A-Z]/|regex:/[0-9]/|regex:/[@$!%*?&]/',
    ]);

    $user = User::create([
        'name' => $validated['name'],
        'email' => $validated['email'],
        'password' => $validated['password'],
        'role' => 'user',
    ]);

    // Send verification email
    $user->sendEmailVerificationNotification();

    $token = $user->createToken('frontend-api')->plainTextToken;

    return response()->json([
        'message' => 'Account created successfully. Please verify your email.',
        'user' => $this->formatUser($user),
    ], 201)
    ->cookie('auth_token', $token, 60, '/', env('SESSION_DOMAIN'),
             env('APP_ENV') === 'production', true, false, 'Strict');
}
```

**Routes:** Add verification route

```php
Route::get('/auth/verify/{id}/{hash}', [UserAuthController::class, 'verify'])
    ->middleware('signed')
    ->name('verification.verify');

Route::post('/auth/resend-verification', [UserAuthController::class, 'resendVerification'])
    ->middleware('throttle:3,1');
```

---

### Fix 10: Implement Promotional Code Validation

**File:** `backend/app/Http/Controllers/API/BookingController.php`

Update the store method:

```php
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

### Fix 11: Add Audit Logging

**Migration:** `database/migrations/2026_06_07_create_audit_logs_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('action');  // 'login_failed', 'login_success', 'booking_created', etc.
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('changes')->nullable();
            $table->string('ip_address', 45);
            $table->string('user_agent')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index('ip_address');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
```

**Trait:** `app/Traits/LogsActivity.php`

```php
<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait LogsActivity
{
    public static function logActivity(string $action, ?array $changes = null)
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'model_type' => static::class,
            'changes' => $changes,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
```

**Usage in Controller:**

```php
use App\Traits\LogsActivity;
use App\Models\AuditLog;

public function login(Request $request)
{
    // ... validation ...
    
    if (!$user || !Hash::check($credentials['password'], $user->password)) {
        AuditLog::create([
            'action' => 'login_failed',
            'ip_address' => $request->ip(),
            'changes' => ['email' => $credentials['email']],
        ]);
        throw ValidationException::withMessages([...]);
    }
    
    AuditLog::create([
        'user_id' => $user->id,
        'action' => 'login_success',
        'ip_address' => $request->ip(),
    ]);
    
    // ... rest of login logic ...
}
```

---

### Fix 12: Enable Encryption for Sensitive Data

**Update Booking Model:** `app/Models/Booking.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Encrypted;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'schedule_id',
        'passenger_name',
        'passenger_phone',
        'passenger_email',
        'passenger_gender',
        'seat_numbers',
        'total_fare',
        'payment_method',
        'status',
        'boarding_point',
        'dropping_point',
    ];

    protected $casts = [
        'passenger_phone' => Encrypted::class,
        'passenger_email' => Encrypted::class,
        'boarding_point' => Encrypted::class,
        'dropping_point' => Encrypted::class,
    ];

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

---

## Production Deployment Checklist

### For cPanel:

1. **Create production `.env`:**
   ```bash
   cp .env.example .env.production
   # Edit with production values
   ```

2. **Set file permissions:**
   ```bash
   chmod 600 .env
   chmod 600 .env.production
   find storage/ -type d -exec chmod 755 {} \;
   find storage/ -type f -exec chmod 644 {} \;
   chmod -R 755 bootstrap/cache
   ```

3. **Move vendor outside public:**
   ```bash
   # In cPanel: Keep vendor in current location
   # Just ensure it's not accessible via web
   # Create .htaccess in vendor/:
   ```

4. **Configure SSL:**
   - Use AutoSSL in cPanel for free Let's Encrypt certificate
   - Force HTTPS: Add to `.htaccess`
   ```
   <IfModule mod_rewrite.c>
       RewriteEngine On
       RewriteCond %{HTTPS} off
       RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   </IfModule>
   ```

5. **Create database backup:**
   ```bash
   mysqldump -u user -p database > backup.sql
   ```

6. **Run migrations:**
   ```bash
   php artisan migrate --force
   ```

### For AWS EC2:

1. **Security Group Rules:**
   ```
   - Port 443 (HTTPS): 0.0.0.0/0
   - Port 80 (HTTP): 0.0.0.0/0 (redirect to 443)
   - Port 22 (SSH): YOUR_IP_ONLY
   - Port 3306 (MySQL): 127.0.0.1 only (or RDS security group)
   ```

2. **Install SSL with Certbot:**
   ```bash
   sudo apt install certbot python3-certbot-nginx -y
   sudo certbot certonly --standalone -d yourdomain.com
   sudo certbot renew --dry-run  # Test auto-renewal
   ```

3. **Configure Nginx:**
   ```nginx
   server {
       listen 443 ssl http2;
       server_name yourdomain.com;

       ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
       ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
       ssl_protocols TLSv1.2 TLSv1.3;
       ssl_ciphers HIGH:!aNULL:!MD5;
       ssl_prefer_server_ciphers on;

       root /var/www/bus-app/public;
       index index.php;

       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
           fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
           include fastcgi_params;
       }
   }

   # Redirect HTTP to HTTPS
   server {
       listen 80;
       server_name yourdomain.com;
       return 301 https://$server_name$request_uri;
   }
   ```

---

**Next Steps:**
1. Run migrations: `php artisan migrate`
2. Seed database: `php artisan db:seed`
3. Test all endpoints
4. Run security tests
5. Deploy to production

