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
use QOR\App\Http\Controllers\Api\V1\EventController;

Route::middleware('throttle:qor-public-api')->group(function () {
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/{id}', [EventController::class, 'show'])->whereNumber('id');
});
