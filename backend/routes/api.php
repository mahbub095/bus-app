<?php

use App\Http\Controllers\StationController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\UserAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/stations', [StationController::class, 'index']);
Route::get('/search', [SearchController::class, 'search']);

Route::post('/auth/register', [UserAuthController::class, 'register']);
Route::post('/auth/login', [UserAuthController::class, 'login']);

Route::get('/promotions', [PromotionController::class, 'index']);
Route::get('/promotions/check', [PromotionController::class, 'check']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [UserAuthController::class, 'logout']);
    Route::get('/auth/me', [UserAuthController::class, 'me']);
    Route::post('/auth/password', [UserAuthController::class, 'updatePassword']);

    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/mine', [BookingController::class, 'mine']);
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);
});

Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
Route::post('/admin/stations', [AdminController::class, 'storeStation']);
Route::post('/admin/buses', [AdminController::class, 'storeBus']);
Route::post('/admin/routes', [AdminController::class, 'storeRoute']);
Route::post('/admin/schedules', [AdminController::class, 'storeSchedule']);
Route::post('/admin/promotions', [AdminController::class, 'storePromotion']);
