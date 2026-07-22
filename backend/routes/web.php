<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\AjaxController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\BusController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RouteController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Controllers\Admin\GatewaySettingsController;
use App\Http\Controllers\Admin\StationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public web
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => view('admin.login'));

Route::get('/payment/callback', [PaymentController::class, 'callback'])->name('payment.callback');
Route::get('/payment/cancel', [PaymentController::class, 'cancel'])->name('payment.cancel');

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
| Admin console (Blade dashboard + form CRUD + session AJAX)
|--------------------------------------------------------------------------
| All controllers live under App\Http\Controllers\Admin\
| Business logic lives in App\Services\
*/

Route::middleware(['auth', 'admin'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Dashboard & Profile
    |--------------------------------------------------------------------------
    */
    Route::get('/admin', [DashboardController::class, 'dashboardView'])->name('admin.dashboard');
    Route::get('/admin/api/dashboard/analytics', [AjaxController::class, 'dashboardAnalytics'])->name('admin.dashboard.analytics');
    Route::post('/admin/profile/password', [AuthController::class, 'updatePassword'])->name('admin.profile.password');

    /*
    |--------------------------------------------------------------------------
    | Admin AJAX APIs (session-based)
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin/api')->group(function () {
        Route::middleware('menu_permission:coach-services')->group(function () {
            Route::get('/coach-services/search', [AjaxController::class, 'searchCoachServices'])->name('admin.coach-services.search');
            Route::post('/schedules/{id}/seats/toggle-block', [AjaxController::class, 'toggleBlockedSeat'])->name('admin.schedules.seats.toggle-block');
        });
        Route::middleware('menu_permission:bookings')->group(function () {
            Route::post('/bookings/{id}/cancel', [AjaxController::class, 'cancelBookingApi'])->name('admin.bookings.cancel.api');
            Route::post('/bookings/{id}/request-cancel', [AjaxController::class, 'requestCancelApi'])->name('admin.bookings.request-cancel.api');
            Route::get('/bookings/logs', [AjaxController::class, 'bookingLogsApi'])->name('admin.bookings.logs.api');
        });
        Route::middleware('menu_permission:cancel-requests')->group(function () {
            Route::get('/cancel-requests/logs', [AjaxController::class, 'cancelRequestsLogsApi'])->name('admin.cancel-requests.logs.api');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Reports Management
    |--------------------------------------------------------------------------
    */
    Route::middleware('menu_permission:reports')->group(function () {
        // ── Report detail pages (full page views) ──────────────────────────
        Route::get('/admin/reports/selling/view',       [ReportController::class, 'detailPage'])->defaults('type', 'selling')->name('admin.reports.page.selling');
        Route::get('/admin/reports/booking/view',       [ReportController::class, 'detailPage'])->defaults('type', 'booking')->name('admin.reports.page.booking');
        Route::get('/admin/reports/revenue/view',       [ReportController::class, 'detailPage'])->defaults('type', 'revenue')->name('admin.reports.page.revenue');
        Route::get('/admin/reports/passenger/view',     [ReportController::class, 'detailPage'])->defaults('type', 'passenger')->name('admin.reports.page.passenger');
        Route::get('/admin/reports/seat-occupancy/view',[ReportController::class, 'detailPage'])->defaults('type', 'seat-occupancy')->name('admin.reports.page.seat-occupancy');
        Route::get('/admin/reports/cancellation/view',  [ReportController::class, 'detailPage'])->defaults('type', 'cancellation')->name('admin.reports.page.cancellation');
        Route::get('/admin/reports/cancel/view',        [ReportController::class, 'detailPage'])->defaults('type', 'cancel')->name('admin.reports.page.cancel');
        Route::get('/admin/reports/refund/view',        [ReportController::class, 'detailPage'])->defaults('type', 'refund')->name('admin.reports.page.refund');
        Route::get('/admin/reports/payment/view',       [ReportController::class, 'detailPage'])->defaults('type', 'payment')->name('admin.reports.page.payment');
        Route::get('/admin/reports/route-sales/view',   [ReportController::class, 'detailPage'])->defaults('type', 'route-sales')->name('admin.reports.page.route-sales');
        Route::get('/admin/reports/agent-sales/view',   [ReportController::class, 'detailPage'])->defaults('type', 'agent-sales')->name('admin.reports.page.agent-sales');

        // Ticket Selling
        Route::get('/admin/reports/selling/preview',      [ReportController::class, 'sellingPreview'])->name('admin.reports.selling.preview');
        Route::get('/admin/reports/selling/export/excel', [ReportController::class, 'sellingExportExcel'])->name('admin.reports.selling.excel');
        Route::get('/admin/reports/selling/export/pdf',   [ReportController::class, 'sellingExportPdf'])->name('admin.reports.selling.pdf');
        // Ticket Cancel
        Route::get('/admin/reports/cancel/preview',       [ReportController::class, 'cancelPreview'])->name('admin.reports.cancel.preview');
        Route::get('/admin/reports/cancel/export/excel',  [ReportController::class, 'cancelExportExcel'])->name('admin.reports.cancel.excel');
        Route::get('/admin/reports/cancel/export/pdf',    [ReportController::class, 'cancelExportPdf'])->name('admin.reports.cancel.pdf');
        // Booking Report
        Route::get('/admin/reports/booking/preview',      [ReportController::class, 'bookingPreview'])->name('admin.reports.booking.preview');
        Route::get('/admin/reports/booking/export/excel', [ReportController::class, 'bookingExportExcel'])->name('admin.reports.booking.excel');
        Route::get('/admin/reports/booking/export/pdf',   [ReportController::class, 'bookingExportPdf'])->name('admin.reports.booking.pdf');
        // Revenue Report
        Route::get('/admin/reports/revenue/preview',      [ReportController::class, 'revenuePreview'])->name('admin.reports.revenue.preview');
        Route::get('/admin/reports/revenue/export/excel', [ReportController::class, 'revenueExportExcel'])->name('admin.reports.revenue.excel');
        Route::get('/admin/reports/revenue/export/pdf',   [ReportController::class, 'revenueExportPdf'])->name('admin.reports.revenue.pdf');
        // Passenger Report
        Route::get('/admin/reports/passenger/preview',      [ReportController::class, 'passengerPreview'])->name('admin.reports.passenger.preview');
        Route::get('/admin/reports/passenger/export/excel', [ReportController::class, 'passengerExportExcel'])->name('admin.reports.passenger.excel');
        Route::get('/admin/reports/passenger/export/pdf',   [ReportController::class, 'passengerExportPdf'])->name('admin.reports.passenger.pdf');
        // Seat Occupancy Report
        Route::get('/admin/reports/seat-occupancy/preview',      [ReportController::class, 'seatOccupancyPreview'])->name('admin.reports.seat-occupancy.preview');
        Route::get('/admin/reports/seat-occupancy/export/excel', [ReportController::class, 'seatOccupancyExportExcel'])->name('admin.reports.seat-occupancy.excel');
        Route::get('/admin/reports/seat-occupancy/export/pdf',   [ReportController::class, 'seatOccupancyExportPdf'])->name('admin.reports.seat-occupancy.pdf');
        // Cancellation Report
        Route::get('/admin/reports/cancellation/preview',      [ReportController::class, 'cancellationPreview'])->name('admin.reports.cancellation.preview');
        Route::get('/admin/reports/cancellation/export/excel', [ReportController::class, 'cancellationExportExcel'])->name('admin.reports.cancellation.excel');
        Route::get('/admin/reports/cancellation/export/pdf',   [ReportController::class, 'cancellationExportPdf'])->name('admin.reports.cancellation.pdf');
        // Refund Report
        Route::get('/admin/reports/refund/preview',      [ReportController::class, 'refundPreview'])->name('admin.reports.refund.preview');
        Route::get('/admin/reports/refund/export/excel', [ReportController::class, 'refundExportExcel'])->name('admin.reports.refund.excel');
        Route::get('/admin/reports/refund/export/pdf',   [ReportController::class, 'refundExportPdf'])->name('admin.reports.refund.pdf');
        // Payment Report
        Route::get('/admin/reports/payment/preview',      [ReportController::class, 'paymentPreview'])->name('admin.reports.payment.preview');
        Route::get('/admin/reports/payment/export/excel', [ReportController::class, 'paymentExportExcel'])->name('admin.reports.payment.excel');
        Route::get('/admin/reports/payment/export/pdf',   [ReportController::class, 'paymentExportPdf'])->name('admin.reports.payment.pdf');
        // Route-wise Sales Report
        Route::get('/admin/reports/route-sales/preview',      [ReportController::class, 'routeSalesPreview'])->name('admin.reports.route-sales.preview');
        Route::get('/admin/reports/route-sales/export/excel', [ReportController::class, 'routeSalesExportExcel'])->name('admin.reports.route-sales.excel');
        Route::get('/admin/reports/route-sales/export/pdf',   [ReportController::class, 'routeSalesExportPdf'])->name('admin.reports.route-sales.pdf');
        // Agent/Counter Sales Report
        Route::get('/admin/reports/agent-sales/preview',      [ReportController::class, 'agentSalesPreview'])->name('admin.reports.agent-sales.preview');
        Route::get('/admin/reports/agent-sales/export/excel', [ReportController::class, 'agentSalesExportExcel'])->name('admin.reports.agent-sales.excel');
        Route::get('/admin/reports/agent-sales/export/pdf',   [ReportController::class, 'agentSalesExportPdf'])->name('admin.reports.agent-sales.pdf');
    });

    /*
    |--------------------------------------------------------------------------
    | Bookings Management
    |--------------------------------------------------------------------------
    */
    Route::middleware('menu_permission:bookings')->group(function () {
        Route::post('/admin/bookings', [BookingController::class, 'store'])->name('admin.bookings.store');
        Route::put('/admin/bookings/{id}', [BookingController::class, 'update'])->name('admin.bookings.update');
        Route::delete('/admin/bookings/{id}', [BookingController::class, 'destroy'])->name('admin.bookings.destroy');
        Route::get('/admin/bookings/{id}/pay', [BookingController::class, 'payAdmin'])->name('admin.bookings.pay');
    });

    /*
    |--------------------------------------------------------------------------
    | Booking Cancel Requests
    |--------------------------------------------------------------------------
    */
    Route::middleware('menu_permission:cancel-requests')->group(function () {
        Route::post('/admin/bookings/{id}/cancel', [BookingController::class, 'cancel'])->name('admin.bookings.cancel');
        Route::post('/admin/bookings/{id}/approve-cancel', [BookingController::class, 'approveCancelRequest'])->name('admin.bookings.approve-cancel');
    });

    /*
    |--------------------------------------------------------------------------
    | Stations Management
    |--------------------------------------------------------------------------
    */
    Route::middleware('menu_permission:stations')->group(function () {
        Route::post('/admin/stations', [StationController::class, 'store'])->name('admin.stations.store');
        Route::put('/admin/stations/{id}', [StationController::class, 'update'])->name('admin.stations.update');
        Route::delete('/admin/stations/{id}', [StationController::class, 'destroy'])->name('admin.stations.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Buses Management
    |--------------------------------------------------------------------------
    */
    Route::middleware('menu_permission:buses')->group(function () {
        Route::post('/admin/buses', [BusController::class, 'store'])->name('admin.buses.store');
        Route::put('/admin/buses/{id}', [BusController::class, 'update'])->name('admin.buses.update');
        Route::delete('/admin/buses/{id}', [BusController::class, 'destroy'])->name('admin.buses.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Routes Management
    |--------------------------------------------------------------------------
    */
    Route::middleware('menu_permission:routes')->group(function () {
        Route::post('/admin/routes', [RouteController::class, 'store'])->name('admin.routes.store');
        Route::put('/admin/routes/{id}', [RouteController::class, 'update'])->name('admin.routes.update');
        Route::delete('/admin/routes/{id}', [RouteController::class, 'destroy'])->name('admin.routes.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Schedules Management
    |--------------------------------------------------------------------------
    */
    Route::middleware('menu_permission:schedules')->group(function () {
        Route::post('/admin/schedules', [ScheduleController::class, 'store'])->name('admin.schedules.store');
        Route::put('/admin/schedules/{id}', [ScheduleController::class, 'update'])->name('admin.schedules.update');
        Route::delete('/admin/schedules/{id}', [ScheduleController::class, 'destroy'])->name('admin.schedules.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Promotions Management
    |--------------------------------------------------------------------------
    */
    Route::middleware('menu_permission:promotions')->group(function () {
        Route::post('/admin/promotions', [PromotionController::class, 'store'])->name('admin.promotions.store');
        Route::put('/admin/promotions/{id}', [PromotionController::class, 'update'])->name('admin.promotions.update');
        Route::delete('/admin/promotions/{id}', [PromotionController::class, 'destroy'])->name('admin.promotions.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | System Settings & Configuration (Super Admin)
    |--------------------------------------------------------------------------
    */
    Route::middleware('super_admin')->group(function () {
        Route::post('/admin/site-settings', [SiteSettingsController::class, 'update'])->name('admin.site-settings.update');
        Route::post('/admin/site-settings/favicon', [SiteSettingsController::class, 'uploadFavicon'])->name('admin.site-settings.favicon');
        
        // Gateways & Integrations separate configuration update routes
        Route::post('/admin/gateway-settings/sms', [GatewaySettingsController::class, 'updateSms'])->name('admin.gateway-settings.update-sms');
        Route::post('/admin/gateway-settings/mail', [GatewaySettingsController::class, 'updateMail'])->name('admin.gateway-settings.update-mail');
        Route::post('/admin/gateway-settings/zinipay', [GatewaySettingsController::class, 'updateZinipay'])->name('admin.gateway-settings.update-zinipay');
        Route::post('/admin/gateway-settings/test-sms', [GatewaySettingsController::class, 'testSms'])->name('admin.gateway-settings.test-sms');
        Route::post('/admin/gateway-settings/test-email', [GatewaySettingsController::class, 'testEmail'])->name('admin.gateway-settings.test-email');
    });

    /*
    |--------------------------------------------------------------------------
    | Users Management
    |--------------------------------------------------------------------------
    */
    Route::middleware('menu_permission:users')->group(function () {
        Route::put('/admin/users/{id}', [UserController::class, 'update'])->name('admin.users.update');
        Route::delete('/admin/users/{id}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    });
});

//1