<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\LocationController;
use Illuminate\Support\Facades\Route;

Route::get('/config', [AuthController::class, 'config'])->middleware('throttle:api');

Route::post('/auth/demo', [AuthController::class, 'demo'])->middleware('throttle:demo');

Route::middleware('throttle:auth')->group(function () {
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);
    Route::post('/auth/exchange', [AuthController::class, 'exchange']);
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::delete('/me', [AuthController::class, 'destroy']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/locations/{location}', [LocationController::class, 'show'])->whereNumber('location');

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/health', [AdminController::class, 'health']);
    });
});
