<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
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
    Route::get('/admin/api/coach-services/search', [AdminController::class, 'searchCoachServices'])->name('admin.coach-services.search');
    Route::post('/admin/api/bookings/{id}/cancel', [AdminController::class, 'cancelBookingApi'])->name('admin.bookings.cancel.api');
    Route::get('/admin/api/bookings/logs', [AdminController::class, 'bookingLogsApi'])->name('admin.bookings.logs.api');
    Route::get('/admin/api/cancel-requests/logs', [AdminController::class, 'cancelRequestsLogsApi'])->name('admin.cancel-requests.logs.api');

    // Reports
    Route::get('/admin/reports/selling/preview', [ReportController::class, 'sellingPreview'])->name('admin.reports.selling.preview');
    Route::get('/admin/reports/selling/export/excel', [ReportController::class, 'sellingExportExcel'])->name('admin.reports.selling.excel');
    Route::get('/admin/reports/selling/export/pdf', [ReportController::class, 'sellingExportPdf'])->name('admin.reports.selling.pdf');
    Route::get('/admin/reports/cancel/preview', [ReportController::class, 'cancelPreview'])->name('admin.reports.cancel.preview');
    Route::get('/admin/reports/cancel/export/excel', [ReportController::class, 'cancelExportExcel'])->name('admin.reports.cancel.excel');
    Route::get('/admin/reports/cancel/export/pdf', [ReportController::class, 'cancelExportPdf'])->name('admin.reports.cancel.pdf');

    // Bookings CRUD
    Route::post('/admin/bookings', [AdminController::class, 'storeBookingWeb'])->name('admin.bookings.store');
    Route::put('/admin/bookings/{id}', [AdminController::class, 'updateBookingWeb'])->name('admin.bookings.update');
    Route::delete('/admin/bookings/{id}', [AdminController::class, 'destroyBookingWeb'])->name('admin.bookings.destroy');
    Route::post('/admin/bookings/{id}/cancel', [AdminController::class, 'cancelBookingWeb'])->name('admin.bookings.cancel');
    Route::post('/admin/bookings/{id}/approve-cancel', [AdminController::class, 'approveCancelRequestWeb'])->name('admin.bookings.approve-cancel');

    // Stations CRUD
    Route::post('/admin/stations', [AdminController::class, 'storeStationWeb'])->name('admin.stations.store');
    Route::put('/admin/stations/{id}', [AdminController::class, 'updateStationWeb'])->name('admin.stations.update');
    Route::delete('/admin/stations/{id}', [AdminController::class, 'destroyStationWeb'])->name('admin.stations.destroy');

    // Coaches (Buses) CRUD
    Route::post('/admin/buses', [AdminController::class, 'storeBusWeb'])->name('admin.buses.store');
    Route::put('/admin/buses/{id}', [AdminController::class, 'updateBusWeb'])->name('admin.buses.update');
    Route::delete('/admin/buses/{id}', [AdminController::class, 'destroyBusWeb'])->name('admin.buses.destroy');

    // Routes CRUD
    Route::post('/admin/routes', [AdminController::class, 'storeRouteWeb'])->name('admin.routes.store');
    Route::put('/admin/routes/{id}', [AdminController::class, 'updateRouteWeb'])->name('admin.routes.update');
    Route::delete('/admin/routes/{id}', [AdminController::class, 'destroyRouteWeb'])->name('admin.routes.destroy');

    // Schedules CRUD
    Route::post('/admin/schedules', [AdminController::class, 'storeScheduleWeb'])->name('admin.schedules.store');
    Route::put('/admin/schedules/{id}', [AdminController::class, 'updateScheduleWeb'])->name('admin.schedules.update');
    Route::delete('/admin/schedules/{id}', [AdminController::class, 'destroyScheduleWeb'])->name('admin.schedules.destroy');

    // Coupons (Promotions) CRUD
    Route::post('/admin/promotions', [AdminController::class, 'storePromotionWeb'])->name('admin.promotions.store');
    Route::put('/admin/promotions/{id}', [AdminController::class, 'updatePromotionWeb'])->name('admin.promotions.update');
    Route::delete('/admin/promotions/{id}', [AdminController::class, 'destroyPromotionWeb'])->name('admin.promotions.destroy');
    Route::post('/admin/sms-config', [AdminController::class, 'updateSmsConfigWeb'])->name('admin.sms-config.update');

    // System Database Migration Actions
    Route::post('/admin/system/migrate', [AdminController::class, 'systemMigrate'])->name('admin.system.migrate');
    Route::post('/admin/system/seed', [AdminController::class, 'systemSeed'])->name('admin.system.seed');
    Route::post('/admin/system/migrate-fresh-seed', [AdminController::class, 'systemMigrateFreshSeed'])->name('admin.system.migrate-fresh-seed');
    
});
