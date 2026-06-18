<?php

use App\Http\Controllers\API\BookingController;
use App\Http\Controllers\API\PromotionController;
use App\Http\Controllers\API\SearchController;
use App\Http\Controllers\API\StationController;
use App\Http\Controllers\API\UserAuthController;
use App\Http\Controllers\API\SiteSettingsApiController;
use App\Http\Controllers\API\AdminController;
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

Route::post('/auth/register', [UserAuthController::class, 'register']);
Route::post('/auth/login', [UserAuthController::class, 'login']);
Route::post('/auth/forgot-password', [UserAuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [UserAuthController::class, 'resetPassword']);

use App\Http\Controllers\PaymentController;
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
});

/*
|--------------------------------------------------------------------------
| Authenticated Admin API (Laravel Sanctum + Admin Check)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'admin', \App\Http\Middleware\CheckMaintenanceMode::class])->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);

    Route::middleware('menu_permission:coach-services')->group(function () {
        Route::get('/admin/coach-services/search', [AdminController::class, 'searchCoachServices']);
        Route::post('/admin/schedules/{id}/seats/toggle-block', [AdminController::class, 'toggleBlockedSeat']);
    });

    Route::middleware('menu_permission:bookings')->group(function () {
        Route::get('/admin/bookings/logs', [AdminController::class, 'bookingLogs']);
        Route::post('/admin/bookings/{id}/cancel', [AdminController::class, 'cancelBooking']);
    });

    Route::middleware('menu_permission:cancel-requests')->group(function () {
        Route::get('/admin/cancel-requests/logs', [AdminController::class, 'cancelRequestsLogs']);
        Route::post('/admin/bookings/{id}/approve-cancel', [AdminController::class, 'approveCancelRequest']);
    });
});

