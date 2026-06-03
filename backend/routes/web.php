<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BusController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SmsConfigController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\API\AdminController as AdminApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReportController;

Route::get('/', function () {
    return view('welcome');
});

// Admin Authentication Public Routes
Route::get('/admin/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/admin/login', [AuthController::class, 'login']);
Route::post('/admin/logout', [AuthController::class, 'logout'])->name('logout');

// Protected Admin Console Area (admin role only)
Route::middleware(['auth', 'admin'])->group(function () {
    
    // Dashboard View
    Route::get('/admin', [AdminController::class, 'dashboardView'])->name('admin.dashboard');
    Route::post('/admin/profile/password', [AuthController::class, 'updatePassword'])->name('admin.profile.password');

    // Coach Services (realtime search + seat map)
    Route::get('/admin/api/coach-services/search', [AdminApiController::class, 'searchCoachServices'])->name('admin.coach-services.search');
    Route::post('/admin/api/bookings/{id}/cancel', [AdminApiController::class, 'cancelBookingApi'])->name('admin.bookings.cancel.api');
    Route::get('/admin/api/bookings/logs', [AdminApiController::class, 'bookingLogsApi'])->name('admin.bookings.logs.api');
    Route::get('/admin/api/cancel-requests/logs', [AdminApiController::class, 'cancelRequestsLogsApi'])->name('admin.cancel-requests.logs.api');

    // Reports
    Route::get('/admin/reports/selling/preview', [ReportController::class, 'sellingPreview'])->name('admin.reports.selling.preview');
    Route::get('/admin/reports/selling/export/excel', [ReportController::class, 'sellingExportExcel'])->name('admin.reports.selling.excel');
    Route::get('/admin/reports/selling/export/pdf', [ReportController::class, 'sellingExportPdf'])->name('admin.reports.selling.pdf');
    Route::get('/admin/reports/cancel/preview', [ReportController::class, 'cancelPreview'])->name('admin.reports.cancel.preview');
    Route::get('/admin/reports/cancel/export/excel', [ReportController::class, 'cancelExportExcel'])->name('admin.reports.cancel.excel');
    Route::get('/admin/reports/cancel/export/pdf', [ReportController::class, 'cancelExportPdf'])->name('admin.reports.cancel.pdf');

    // Bookings CRUD
    Route::post('/admin/bookings', [BookingController::class, 'store'])->name('admin.bookings.store');
    Route::put('/admin/bookings/{id}', [BookingController::class, 'update'])->name('admin.bookings.update');
    Route::delete('/admin/bookings/{id}', [BookingController::class, 'destroy'])->name('admin.bookings.destroy');
    Route::post('/admin/bookings/{id}/cancel', [BookingController::class, 'cancel'])->name('admin.bookings.cancel');
    Route::post('/admin/bookings/{id}/approve-cancel', [BookingController::class, 'approveCancelRequest'])->name('admin.bookings.approve-cancel');

    // Stations CRUD
    Route::post('/admin/stations', [StationController::class, 'store'])->name('admin.stations.store');
    Route::put('/admin/stations/{id}', [StationController::class, 'update'])->name('admin.stations.update');
    Route::delete('/admin/stations/{id}', [StationController::class, 'destroy'])->name('admin.stations.destroy');

    // Coaches (Buses) CRUD
    Route::post('/admin/buses', [BusController::class, 'store'])->name('admin.buses.store');
    Route::put('/admin/buses/{id}', [BusController::class, 'update'])->name('admin.buses.update');
    Route::delete('/admin/buses/{id}', [BusController::class, 'destroy'])->name('admin.buses.destroy');

    // Routes CRUD
    Route::post('/admin/routes', [RouteController::class, 'store'])->name('admin.routes.store');
    Route::put('/admin/routes/{id}', [RouteController::class, 'update'])->name('admin.routes.update');
    Route::delete('/admin/routes/{id}', [RouteController::class, 'destroy'])->name('admin.routes.destroy');

    // Schedules CRUD
    Route::post('/admin/schedules', [ScheduleController::class, 'store'])->name('admin.schedules.store');
    Route::put('/admin/schedules/{id}', [ScheduleController::class, 'update'])->name('admin.schedules.update');
    Route::delete('/admin/schedules/{id}', [ScheduleController::class, 'destroy'])->name('admin.schedules.destroy');

    // Coupons (Promotions) CRUD
    Route::post('/admin/promotions', [PromotionController::class, 'store'])->name('admin.promotions.store');
    Route::put('/admin/promotions/{id}', [PromotionController::class, 'update'])->name('admin.promotions.update');
    Route::delete('/admin/promotions/{id}', [PromotionController::class, 'destroy'])->name('admin.promotions.destroy');
    Route::post('/admin/sms-config', [SmsConfigController::class, 'update'])->name('admin.sms-config.update');
    Route::post('/admin/sms-config/test', [SmsConfigController::class, 'testSend'])->name('admin.sms-config.test');

    // System Database Migration Actions
    Route::post('/admin/system/migrate', [SystemController::class, 'migrate'])->name('admin.system.migrate');
    Route::post('/admin/system/seed', [SystemController::class, 'seed'])->name('admin.system.seed');
    Route::post('/admin/system/migrate-fresh-seed', [SystemController::class, 'migrateFreshSeed'])->name('admin.system.migrate-fresh-seed');
    
});
