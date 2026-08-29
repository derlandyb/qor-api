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
use QOR\App\Http\Controllers\Api\V1\FavoriteController;
use QOR\App\Http\Controllers\Api\V1\ProfileController;

Route::middleware('throttle:qor-public-api')->group(function () {
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/{id}', [EventController::class, 'show'])->whereNumber('id');
});

Route::post('/events/{id}/favorite', [FavoriteController::class, 'toggle'])
    ->whereNumber('id')
    ->middleware(['auth:fan', 'guard.fan']);

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

Route::prefix('profile')->middleware(['auth:fan', 'guard.fan'])->group(function () {
    Route::get('/', [ProfileController::class, 'show']);
    Route::patch('/', [ProfileController::class, 'update']);
    Route::post('/picture', [ProfileController::class, 'updatePicture']);
    Route::get('/data-rights/access', [ProfileController::class, 'dataRightsAccess']);
    Route::get('/data-rights/export', [ProfileController::class, 'dataRightsExport']);
    Route::post('/data-rights/delete', [ProfileController::class, 'dataRightsDelete']);
    Route::post('/data-rights/revoke', [ProfileController::class, 'dataRightsRevoke']);
    Route::get('/favorites', [FavoriteController::class, 'index']);
});
