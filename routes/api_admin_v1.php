<?php

/*
|--------------------------------------------------------------------------
| API Admin v1 Routes
|--------------------------------------------------------------------------
|
| Admin-panel surface (Venue Admin/Promoter/Super Admin) — /api/admin/v1/*.
| See ARCHITECTURE.md §3.
|
*/

use Illuminate\Support\Facades\Route;
use QOR\App\Http\Controllers\Api\AdminV1\AccountApprovalController;
use QOR\App\Http\Controllers\Api\AdminV1\AdminAuthController;
use QOR\App\Http\Controllers\Api\AdminV1\DashboardController;
use QOR\App\Http\Controllers\Api\AdminV1\EventApprovalController;
use QOR\App\Http\Controllers\Api\AdminV1\EventController;
use QOR\App\Http\Controllers\Api\AdminV1\PlanController;
use QOR\App\Http\Controllers\Api\AdminV1\PromoterController;
use QOR\App\Http\Controllers\Api\AdminV1\VenueController;

Route::middleware('throttle:qor-auth')->group(function () {
    Route::post('/venues/register', [VenueController::class, 'register']);
    Route::post('/promoters/register', [PromoterController::class, 'register']);
    Route::post('/auth/login', [AdminAuthController::class, 'login']);
});

Route::post('/auth/logout', [AdminAuthController::class, 'logout'])
    ->middleware(['auth:admin', 'guard.admin']);

Route::middleware(['auth:admin', 'guard.admin'])->group(function () {
    Route::patch('/venues/me', [VenueController::class, 'update']);
    Route::patch('/promoters/me', [PromoterController::class, 'update']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
});

Route::prefix('events')->middleware(['auth:admin', 'guard.admin'])->group(function () {
    Route::get('/', [EventController::class, 'index']);
    Route::post('/', [EventController::class, 'store']);
    Route::post('/{id}/submit', [EventController::class, 'submit'])->whereNumber('id');
    Route::patch('/{id}', [EventController::class, 'update'])->whereNumber('id');
    Route::post('/{id}/duplicate', [EventController::class, 'duplicate'])->whereNumber('id');
    Route::post('/{id}/cancel', [EventController::class, 'cancel'])->whereNumber('id');
    Route::delete('/{id}', [EventController::class, 'destroy'])->whereNumber('id');
});

Route::prefix('approvals')->middleware(['auth:admin', 'guard.admin', 'guard.super-admin'])->group(function () {
    Route::get('/accounts', [AccountApprovalController::class, 'index']);
    Route::post('/accounts/{accountType}/{id}/decide', [AccountApprovalController::class, 'decide'])->whereNumber('id');

    Route::get('/events', [EventApprovalController::class, 'index']);
    Route::post('/events/{id}/decide', [EventApprovalController::class, 'decide'])->whereNumber('id');
});

Route::prefix('plans')->middleware(['auth:admin', 'guard.admin', 'guard.super-admin'])->group(function () {
    Route::get('/', [PlanController::class, 'index']);
    Route::post('/', [PlanController::class, 'store']);
    Route::patch('/{id}', [PlanController::class, 'update'])->whereNumber('id');
    Route::post('/{id}/deactivate', [PlanController::class, 'deactivate'])->whereNumber('id');
});
