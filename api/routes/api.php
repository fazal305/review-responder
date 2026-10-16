<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CronController;
use App\Http\Controllers\Api\DraftController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/config', [AuthController::class, 'config'])->middleware('throttle:api');

Route::post('/auth/demo', [AuthController::class, 'demo'])->middleware('throttle:demo');

Route::post('/cron/run', CronController::class)->middleware('throttle:auth');

Route::middleware('throttle:auth')->group(function () {
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);
    Route::post('/auth/exchange', [AuthController::class, 'exchange']);
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // Reachable before the terms are accepted, so the app can ask for them (or the owner can leave).
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/me/accept-terms', [AuthController::class, 'acceptTerms']);
    Route::delete('/me', [AuthController::class, 'destroy']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::middleware('terms')->group(function () {
        Route::get('/settings', [SettingsController::class, 'show']);
        Route::put('/settings', [SettingsController::class, 'update']);

        Route::get('/locations', [LocationController::class, 'index']);
        Route::post('/locations/refresh', [LocationController::class, 'refresh'])->middleware('throttle:google-refresh');
        Route::get('/locations/{location}', [LocationController::class, 'show'])->whereNumber('location');
        Route::patch('/locations/{location}', [LocationController::class, 'update'])->whereNumber('location');

        Route::get('/queue', [DraftController::class, 'queue']);
        Route::whereNumber('draft')->prefix('drafts/{draft}')->group(function () {
            Route::patch('/', [DraftController::class, 'update']);
            Route::post('/approve', [DraftController::class, 'approve'])->name('drafts.approve')->middleware('throttle:post-reply');
            Route::post('/dismiss', [DraftController::class, 'dismiss']);
            Route::post('/regenerate', [DraftController::class, 'regenerate'])->middleware('throttle:regenerate');
        });

        Route::middleware('admin')->prefix('admin')->group(function () {
            Route::get('/health', [AdminController::class, 'health']);
            Route::get('/usage', [AdminController::class, 'usage']);
        });
    });
});
