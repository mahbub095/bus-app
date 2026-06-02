<?php

use App\Http\Controllers\StationController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider or bootstrap/app.php.
|
*/

Route::get('/stations', [StationController::class, 'index']);
Route::get('/search', [SearchController::class, 'search']);

Route::post('/bookings', [BookingController::class, 'store']);
Route::get('/bookings/search', [BookingController::class, 'search']);
Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);

Route::get('/promotions', [PromotionController::class, 'index']);
Route::get('/promotions/check', [PromotionController::class, 'check']);

Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
Route::post('/admin/stations', [AdminController::class, 'storeStation']);
Route::post('/admin/buses', [AdminController::class, 'storeBus']);
Route::post('/admin/routes', [AdminController::class, 'storeRoute']);
Route::post('/admin/schedules', [AdminController::class, 'storeSchedule']);
Route::post('/admin/promotions', [AdminController::class, 'storePromotion']);
