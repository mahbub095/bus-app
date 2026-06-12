<?php

use App\Http\Controllers\Admin\AjaxController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BusController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\SiteSettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public web
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Admin authentication (session)
|--------------------------------------------------------------------------
*/
Route::get('/admin/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/admin/login', [AuthController::class, 'login']);
Route::post('/admin/logout', [AuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Admin console (Blade + form CRUD + session AJAX)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'admin'])->group(function () {
    // Dashboard
    Route::get('/admin', [AdminController::class, 'dashboardView'])->name('admin.dashboard');
    Route::post('/admin/profile/password', [AuthController::class, 'updatePassword'])->name('admin.profile.password');

    // Admin dashboard AJAX (session auth — not public /api routes)
    Route::prefix('admin/api')->group(function () {
        Route::middleware('menu_permission:coach-services')->group(function () {
            Route::get('/coach-services/search', [AjaxController::class, 'searchCoachServices'])->name('admin.coach-services.search');
            Route::post('/schedules/{id}/seats/toggle-block', [AjaxController::class, 'toggleBlockedSeat'])->name('admin.schedules.seats.toggle-block');
        });
        Route::middleware('menu_permission:bookings')->group(function () {
            Route::post('/bookings/{id}/cancel', [AjaxController::class, 'cancelBookingApi'])->name('admin.bookings.cancel.api');
            Route::get('/bookings/logs', [AjaxController::class, 'bookingLogsApi'])->name('admin.bookings.logs.api');
        });
        Route::middleware('menu_permission:cancel-requests')->group(function () {
            Route::get('/cancel-requests/logs', [AjaxController::class, 'cancelRequestsLogsApi'])->name('admin.cancel-requests.logs.api');
        });
    });

    // Reports
    Route::middleware('menu_permission:reports')->group(function () {
        Route::get('/admin/reports/selling/preview', [ReportController::class, 'sellingPreview'])->name('admin.reports.selling.preview');
        Route::get('/admin/reports/selling/export/excel', [ReportController::class, 'sellingExportExcel'])->name('admin.reports.selling.excel');
        Route::get('/admin/reports/selling/export/pdf', [ReportController::class, 'sellingExportPdf'])->name('admin.reports.selling.pdf');
        Route::get('/admin/reports/cancel/preview', [ReportController::class, 'cancelPreview'])->name('admin.reports.cancel.preview');
        Route::get('/admin/reports/cancel/export/excel', [ReportController::class, 'cancelExportExcel'])->name('admin.reports.cancel.excel');
        Route::get('/admin/reports/cancel/export/pdf', [ReportController::class, 'cancelExportPdf'])->name('admin.reports.cancel.pdf');
    });

    // Bookings CRUD
    Route::middleware('menu_permission:bookings')->group(function () {
        Route::post('/admin/bookings', [BookingController::class, 'store'])->name('admin.bookings.store');
        Route::put('/admin/bookings/{id}', [BookingController::class, 'update'])->name('admin.bookings.update');
        Route::delete('/admin/bookings/{id}', [BookingController::class, 'destroy'])->name('admin.bookings.destroy');
    });

    // Cancel requests
    Route::middleware('menu_permission:cancel-requests')->group(function () {
        Route::post('/admin/bookings/{id}/cancel', [BookingController::class, 'cancel'])->name('admin.bookings.cancel');
        Route::post('/admin/bookings/{id}/approve-cancel', [BookingController::class, 'approveCancelRequest'])->name('admin.bookings.approve-cancel');
    });

    // Stations CRUD
    Route::middleware('menu_permission:stations')->group(function () {
        Route::post('/admin/stations', [StationController::class, 'store'])->name('admin.stations.store');
        Route::put('/admin/stations/{id}', [StationController::class, 'update'])->name('admin.stations.update');
        Route::delete('/admin/stations/{id}', [StationController::class, 'destroy'])->name('admin.stations.destroy');
    });

    // Coaches (Buses) CRUD
    Route::middleware('menu_permission:buses')->group(function () {
        Route::post('/admin/buses', [BusController::class, 'store'])->name('admin.buses.store');
        Route::put('/admin/buses/{id}', [BusController::class, 'update'])->name('admin.buses.update');
        Route::delete('/admin/buses/{id}', [BusController::class, 'destroy'])->name('admin.buses.destroy');
    });

    // Routes CRUD
    Route::middleware('menu_permission:routes')->group(function () {
        Route::post('/admin/routes', [RouteController::class, 'store'])->name('admin.routes.store');
        Route::put('/admin/routes/{id}', [RouteController::class, 'update'])->name('admin.routes.update');
        Route::delete('/admin/routes/{id}', [RouteController::class, 'destroy'])->name('admin.routes.destroy');
    });

    // Schedules CRUD
    Route::middleware('menu_permission:schedules')->group(function () {
        Route::post('/admin/schedules', [ScheduleController::class, 'store'])->name('admin.schedules.store');
        Route::put('/admin/schedules/{id}', [ScheduleController::class, 'update'])->name('admin.schedules.update');
        Route::delete('/admin/schedules/{id}', [ScheduleController::class, 'destroy'])->name('admin.schedules.destroy');
    });

    // Coupons (Promotions) CRUD
    Route::middleware('menu_permission:promotions')->group(function () {
        Route::post('/admin/promotions', [PromotionController::class, 'store'])->name('admin.promotions.store');
        Route::put('/admin/promotions/{id}', [PromotionController::class, 'update'])->name('admin.promotions.update');
        Route::delete('/admin/promotions/{id}', [PromotionController::class, 'destroy'])->name('admin.promotions.destroy');
    });

    // System Database Migration Actions & Site Settings (restricted to super_admin)
    Route::middleware('super_admin')->group(function () {
        Route::post('/admin/system/migrate', [SystemController::class, 'migrate'])->name('admin.system.migrate');
        Route::post('/admin/system/seed', [SystemController::class, 'seed'])->name('admin.system.seed');
        Route::post('/admin/system/migrate-fresh-seed', [SystemController::class, 'migrateFreshSeed'])->name('admin.system.migrate-fresh-seed');

        Route::post('/admin/site-settings', [SiteSettingsController::class, 'update'])->name('admin.site-settings.update');
        Route::post('/admin/site-settings/favicon', [SiteSettingsController::class, 'uploadFavicon'])->name('admin.site-settings.favicon');
    });

    // Users update & delete (restricted to users menu permission)
    Route::middleware('menu_permission:users')->group(function () {
        Route::put('/admin/users/{id}', [\App\Http\Controllers\UserController::class, 'update'])->name('admin.users.update');
        Route::delete('/admin/users/{id}', [\App\Http\Controllers\UserController::class, 'destroy'])->name('admin.users.destroy');
    });
});

use App\Http\Controllers\PaymentController;

Route::get('/payment/callback', [PaymentController::class, 'callback'])->name('payment.callback');
Route::get('/payment/cancel', [PaymentController::class, 'cancel'])->name('payment.cancel');

