<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\WorkingHourController;
use App\Http\Controllers\BreakPeriodController;
use App\Http\Controllers\AdminBookingController;

// Public routes
Route::get('/services', [ServiceController::class, 'index']);
Route::get('/available-slots', [BookingController::class, 'getAvailableSlots']);
Route::get('/working-days', [BookingController::class, 'getWorkingDays']);
Route::post('/bookings', [BookingController::class, 'store']);
Route::get('/bookings', [BookingController::class, 'index']);

// Admin routes (no auth)
Route::prefix('admin')->group(function () {
    Route::apiResource('services', ServiceController::class)->except(['index']);
    Route::apiResource('working-hours', WorkingHourController::class);

    // Break periods routes
    Route::get('/working-hours/{workingHour}/break-periods', [BreakPeriodController::class, 'index']);
    Route::post('/working-hours/{workingHour}/break-periods', [BreakPeriodController::class, 'store']);
    Route::put('/break-periods/{breakPeriod}', [BreakPeriodController::class, 'update']);
    Route::delete('/break-periods/{breakPeriod}', [BreakPeriodController::class, 'destroy']);

    // Bookings routes
    Route::get('/bookings', [AdminBookingController::class, 'index']);
    Route::put('/bookings/{id}', [AdminBookingController::class, 'update']);
    Route::delete('/bookings/{id}', [AdminBookingController::class, 'destroy']);
});
