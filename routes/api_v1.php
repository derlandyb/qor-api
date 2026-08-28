<?php

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
|
| End-user surfaces (mobile app, website, landing page) — /api/v1/*.
| Public/fan-facing traffic. See ARCHITECTURE.md §3.
|
*/

use Illuminate\Support\Facades\Route;
use QOR\App\Http\Controllers\Api\V1\AuthController;
use QOR\App\Http\Controllers\Api\V1\EventController;

Route::middleware('throttle:qor-public-api')->group(function () {
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/{id}', [EventController::class, 'show'])->whereNumber('id');
});

Route::prefix('auth')->middleware('throttle:qor-auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/google', [AuthController::class, 'google']);
    Route::post('/password/forgot', [AuthController::class, 'forgotPassword']);
    Route::post('/password/reset', [AuthController::class, 'resetPasswordConfirm']);
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerification']);

    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->whereNumber('id')
        ->middleware('signed')
        ->name('verification.verify');

    Route::get('/email/pending/verify/{id}/{hash}', [AuthController::class, 'confirmPendingEmail'])
        ->whereNumber('id')
        ->middleware('signed')
        ->name('email.pending.verify');
});

Route::post('/auth/logout', [AuthController::class, 'logout'])
    ->middleware(['auth:fan', 'guard.fan']);
