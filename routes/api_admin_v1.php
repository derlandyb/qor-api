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
use QOR\App\Http\Controllers\Api\AdminV1\EventController;
use QOR\App\Http\Controllers\Api\AdminV1\PromoterController;
use QOR\App\Http\Controllers\Api\AdminV1\VenueController;

Route::middleware('throttle:qor-auth')->group(function () {
    Route::post('/venues/register', [VenueController::class, 'register']);
    Route::post('/promoters/register', [PromoterController::class, 'register']);
});

Route::prefix('events')->middleware(['auth:admin', 'guard.admin'])->group(function () {
    Route::get('/', [EventController::class, 'index']);
    Route::post('/', [EventController::class, 'store']);
    Route::post('/{id}/submit', [EventController::class, 'submit'])->whereNumber('id');
    Route::delete('/{id}', [EventController::class, 'destroy'])->whereNumber('id');
});

Route::prefix('approvals')->middleware(['auth:admin', 'guard.admin', 'guard.super-admin'])->group(function () {
    Route::get('/accounts', [AccountApprovalController::class, 'index']);
    Route::post('/accounts/{accountType}/{id}/decide', [AccountApprovalController::class, 'decide'])->whereNumber('id');
});
