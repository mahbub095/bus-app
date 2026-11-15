<?php

use App\Http\Controllers\API\Admin\AdminAuthController;
use App\Http\Controllers\API\Admin\BookingApiController as AdminBookingApiController;
use App\Http\Controllers\API\Admin\BusApiController;
use App\Http\Controllers\API\Admin\CancelRequestApiController;
use App\Http\Controllers\API\Admin\CoachServiceApiController;
use App\Http\Controllers\API\Admin\DashboardApiController;
use App\Http\Controllers\API\Admin\PromotionApiController;
use App\Http\Controllers\API\Admin\ReportApiController;
use App\Http\Controllers\API\Admin\RouteApiController;
use App\Http\Controllers\API\Admin\ScheduleApiController;
use App\Http\Controllers\API\Admin\SiteSettingsApiController as AdminSiteSettingsApiController;
use App\Http\Controllers\API\Admin\StationApiController;
use App\Http\Controllers\API\Admin\UserApiController;
use App\Http\Controllers\API\BookingController;
use App\Http\Controllers\API\PromotionController;
use App\Http\Controllers\API\SearchController;
use App\Http\Controllers\API\StationController;
use App\Http\Controllers\API\UserAuthController;
use App\Http\Controllers\API\SiteSettingsApiController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Site Settings (always accessible, even during maintenance)
|--------------------------------------------------------------------------
*/

Route::get('/site-settings', [SiteSettingsApiController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Public customer API (React frontend — /api prefix)
|--------------------------------------------------------------------------
*/

Route::middleware(\App\Http\Middleware\CheckMaintenanceMode::class)->group(function () {
    Route::get('/stations', [StationController::class, 'index']);
    Route::get('/search', [SearchController::class, 'search']);

    Route::get('/promotions', [PromotionController::class, 'index']);
    Route::get('/promotions/check', [PromotionController::class, 'check']);
});

/*
|--------------------------------------------------------------------------
| Customer Authentication (Guest routes)
| Rate-limited: 10 attempts per minute per IP to prevent brute-force.
|--------------------------------------------------------------------------
*/

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/auth/register', [UserAuthController::class, 'register']);
    Route::post('/auth/login', [UserAuthController::class, 'login']);
    Route::post('/auth/forgot-password', [UserAuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [UserAuthController::class, 'resetPassword']);
});

/*
|--------------------------------------------------------------------------
| Public Webhooks & External callback routes
|--------------------------------------------------------------------------
*/

Route::post('/payment/webhook', [PaymentController::class, 'webhook'])->name('payment.webhook');
Route::get('/bookings/public/{id}', [BookingController::class, 'showPublic'])->name('bookings.show_public');

/*
|--------------------------------------------------------------------------
| Authenticated customer API (Laravel Sanctum)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckMaintenanceMode::class])->group(function () {
    Route::post('/auth/logout', [UserAuthController::class, 'logout']);
    Route::get('/auth/me', [UserAuthController::class, 'me']);
    Route::post('/auth/password', [UserAuthController::class, 'updatePassword']);

    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/mine', [BookingController::class, 'mine']);
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);
    Route::post('/bookings/{id}/pay', [BookingController::class, 'pay']);

    Route::post('/seats/hold', [BookingController::class, 'holdSeat']);
    Route::post('/seats/release', [BookingController::class, 'releaseSeat']);
});

/*
|--------------------------------------------------------------------------
| Admin API (Sanctum token) — mirrors the admin dashboard
|--------------------------------------------------------------------------
| Login issues a token whose lifetime follows the admin idle timeout saved in
| Site Settings → Session Security. Every other endpoint answers with the same
| data, validation and permission rules the Blade dashboard uses.
*/

Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('api.admin.login');

    Route::middleware(['auth:sanctum', 'api_admin'])->group(function () {
        /*
        |------------------------------------------------------------------
        | Profile
        |------------------------------------------------------------------
        */
        Route::get('/me', [AdminAuthController::class, 'me'])->name('api.admin.me');
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('api.admin.logout');
        Route::post('/password', [AdminAuthController::class, 'updatePassword'])
            ->middleware('api_menu:profile')
            ->name('api.admin.password');

        /*
        |------------------------------------------------------------------
        | Dashboard (KPI cards, charts, first page of each list)
        |------------------------------------------------------------------
        */
        Route::get('/dashboard', [DashboardApiController::class, 'index'])->name('api.admin.dashboard');
        Route::get('/dashboard/analytics', [DashboardApiController::class, 'analytics'])
            ->name('api.admin.dashboard.analytics');

        /*
        |------------------------------------------------------------------
        | Stations
        |------------------------------------------------------------------
        */
        Route::middleware('api_menu:stations')->group(function () {
            Route::get('/stations', [StationApiController::class, 'index'])->name('api.admin.stations.index');
            Route::post('/stations', [StationApiController::class, 'store'])->name('api.admin.stations.store');
            Route::get('/stations/{id}', [StationApiController::class, 'show'])->whereNumber('id')->name('api.admin.stations.show');
            Route::match(['put', 'patch'], '/stations/{id}', [StationApiController::class, 'update'])->whereNumber('id')->name('api.admin.stations.update');
            Route::delete('/stations/{id}', [StationApiController::class, 'destroy'])->whereNumber('id')->name('api.admin.stations.destroy');
        });

        /*
        |------------------------------------------------------------------
        | Coaches (buses)
        |------------------------------------------------------------------
        */
        Route::middleware('api_menu:buses')->group(function () {
            Route::get('/buses', [BusApiController::class, 'index'])->name('api.admin.buses.index');
            Route::post('/buses', [BusApiController::class, 'store'])->name('api.admin.buses.store');
            Route::get('/buses/{id}', [BusApiController::class, 'show'])->whereNumber('id')->name('api.admin.buses.show');
            Route::match(['put', 'patch'], '/buses/{id}', [BusApiController::class, 'update'])->whereNumber('id')->name('api.admin.buses.update');
            Route::delete('/buses/{id}', [BusApiController::class, 'destroy'])->whereNumber('id')->name('api.admin.buses.destroy');
        });

        /*
        |------------------------------------------------------------------
        | Routes
        |------------------------------------------------------------------
        */
        Route::middleware('api_menu:routes')->group(function () {
            Route::get('/routes', [RouteApiController::class, 'index'])->name('api.admin.routes.index');
            Route::post('/routes', [RouteApiController::class, 'store'])->name('api.admin.routes.store');
            Route::get('/routes/{id}', [RouteApiController::class, 'show'])->whereNumber('id')->name('api.admin.routes.show');
            Route::match(['put', 'patch'], '/routes/{id}', [RouteApiController::class, 'update'])->whereNumber('id')->name('api.admin.routes.update');
            Route::delete('/routes/{id}', [RouteApiController::class, 'destroy'])->whereNumber('id')->name('api.admin.routes.destroy');
        });

        /*
        |------------------------------------------------------------------
        | Schedules
        |------------------------------------------------------------------
        */
        Route::middleware('api_menu:schedules')->group(function () {
            Route::get('/schedules', [ScheduleApiController::class, 'index'])->name('api.admin.schedules.index');
            Route::post('/schedules', [ScheduleApiController::class, 'store'])->name('api.admin.schedules.store');
            Route::get('/schedules/{id}', [ScheduleApiController::class, 'show'])->whereNumber('id')->name('api.admin.schedules.show');
            Route::match(['put', 'patch'], '/schedules/{id}', [ScheduleApiController::class, 'update'])->whereNumber('id')->name('api.admin.schedules.update');
            Route::delete('/schedules/{id}', [ScheduleApiController::class, 'destroy'])->whereNumber('id')->name('api.admin.schedules.destroy');
        });

        /*
        |------------------------------------------------------------------
        | Promotions
        |------------------------------------------------------------------
        */
        Route::middleware('api_menu:promotions')->group(function () {
            Route::get('/promotions', [PromotionApiController::class, 'index'])->name('api.admin.promotions.index');
            Route::post('/promotions', [PromotionApiController::class, 'store'])->name('api.admin.promotions.store');
            Route::get('/promotions/{id}', [PromotionApiController::class, 'show'])->whereNumber('id')->name('api.admin.promotions.show');
            Route::match(['put', 'patch'], '/promotions/{id}', [PromotionApiController::class, 'update'])->whereNumber('id')->name('api.admin.promotions.update');
            Route::delete('/promotions/{id}', [PromotionApiController::class, 'destroy'])->whereNumber('id')->name('api.admin.promotions.destroy');
        });

        /*
        |------------------------------------------------------------------
        | Bookings
        |------------------------------------------------------------------
        */
        Route::middleware('api_menu:bookings')->group(function () {
            Route::get('/bookings', [AdminBookingApiController::class, 'index'])->name('api.admin.bookings.index');
            Route::post('/bookings', [AdminBookingApiController::class, 'store'])->name('api.admin.bookings.store');
            Route::get('/bookings/{id}', [AdminBookingApiController::class, 'show'])->whereNumber('id')->name('api.admin.bookings.show');
            Route::match(['put', 'patch'], '/bookings/{id}', [AdminBookingApiController::class, 'update'])->whereNumber('id')->name('api.admin.bookings.update');
            Route::delete('/bookings/{id}', [AdminBookingApiController::class, 'destroy'])->whereNumber('id')->name('api.admin.bookings.destroy');
            Route::post('/bookings/{id}/cancel', [AdminBookingApiController::class, 'cancel'])->whereNumber('id')->name('api.admin.bookings.cancel');
            Route::post('/bookings/{id}/request-cancel', [AdminBookingApiController::class, 'requestCancel'])->whereNumber('id')->name('api.admin.bookings.request-cancel');
            Route::post('/bookings/{id}/pay', [AdminBookingApiController::class, 'pay'])->whereNumber('id')->name('api.admin.bookings.pay');
        });

        /*
        |------------------------------------------------------------------
        | Cancellation requests
        |------------------------------------------------------------------
        */
        Route::middleware('api_menu:cancel-requests')->group(function () {
            Route::get('/cancel-requests', [CancelRequestApiController::class, 'index'])->name('api.admin.cancel-requests.index');
            Route::post('/cancel-requests/{id}/approve', [CancelRequestApiController::class, 'approve'])->whereNumber('id')->name('api.admin.cancel-requests.approve');
        });

        /*
        |------------------------------------------------------------------
        | Coach services (search + seat operations)
        |------------------------------------------------------------------
        */
        Route::middleware('api_menu:coach-services')->group(function () {
            Route::get('/coach-services', [CoachServiceApiController::class, 'index'])->name('api.admin.coach-services.index');
            Route::post('/schedules/{id}/seats/toggle-block', [CoachServiceApiController::class, 'toggleBlockedSeat'])->whereNumber('id')->name('api.admin.seats.toggle-block');
            Route::post('/seats/hold', [CoachServiceApiController::class, 'holdSeat'])->name('api.admin.seats.hold');
            Route::post('/seats/release', [CoachServiceApiController::class, 'releaseSeat'])->name('api.admin.seats.release');
        });

        /*
        |------------------------------------------------------------------
        | Users & permissions
        |------------------------------------------------------------------
        */
        Route::middleware('api_menu:users')->group(function () {
            Route::get('/users', [UserApiController::class, 'index'])->name('api.admin.users.index');
            Route::post('/users', [UserApiController::class, 'store'])->name('api.admin.users.store');
            Route::get('/users/{id}', [UserApiController::class, 'show'])->whereNumber('id')->name('api.admin.users.show');
            Route::match(['put', 'patch'], '/users/{id}', [UserApiController::class, 'update'])->whereNumber('id')->name('api.admin.users.update');
            Route::delete('/users/{id}', [UserApiController::class, 'destroy'])->whereNumber('id')->name('api.admin.users.destroy');
        });

        /*
        |------------------------------------------------------------------
        | Site settings (incl. Session Security)
        |------------------------------------------------------------------
        */
        Route::middleware('api_menu:site-settings')->group(function () {
            Route::get('/site-settings', [AdminSiteSettingsApiController::class, 'index'])->name('api.admin.site-settings.index');
            Route::match(['put', 'patch'], '/site-settings', [AdminSiteSettingsApiController::class, 'update'])->name('api.admin.site-settings.update');
            Route::post('/site-settings/favicon', [AdminSiteSettingsApiController::class, 'uploadFavicon'])->name('api.admin.site-settings.favicon');
            Route::post('/site-settings/logo', [AdminSiteSettingsApiController::class, 'uploadLogo'])->name('api.admin.site-settings.logo');
            Route::delete('/site-settings/logo', [AdminSiteSettingsApiController::class, 'deleteLogo'])->name('api.admin.site-settings.logo.delete');
        });

        /*
        |------------------------------------------------------------------
        | Reports (previews; Excel/PDF exports stay on the web routes)
        |------------------------------------------------------------------
        */
        Route::middleware('api_menu:reports')->group(function () {
            Route::get('/reports', [ReportApiController::class, 'index'])->name('api.admin.reports.index');
            Route::get('/reports/filters', [ReportApiController::class, 'filters'])->name('api.admin.reports.filters');
            Route::get('/reports/{type}', [ReportApiController::class, 'show'])->name('api.admin.reports.show');
        });
    });
});

