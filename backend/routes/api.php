<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\WorkingHourController;

// Public routes
Route::get('/services', [ServiceController::class, 'index']);
Route::get('/available-slots', [BookingController::class, 'getAvailableSlots']);
Route::post('/bookings', [BookingController::class, 'store']);
Route::get('/bookings', [BookingController::class, 'index']);

// Admin routes (no auth)
Route::prefix('admin')->group(function () {
    Route::apiResource('services', ServiceController::class)->except(['index']);
    Route::apiResource('working-hours', WorkingHourController::class);
});