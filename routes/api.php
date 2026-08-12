<?php

use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\FilterOptionsController;
use App\Http\Controllers\Api\MapController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Deliberately outside auth:sanctum — FEED-001 requires zero-auth, zero-permission access.
Route::get('/events', [EventController::class, 'index']);
// Deliberately outside auth:sanctum — MAP-005 requires zero location permission, and the map is visitor-only.
// Registered before /events/{id} would otherwise be unreachable — Laravel matches routes in
// registration order, and {id} is an unconstrained wildcard that would swallow "map" as an id.
Route::get('/events/map', [MapController::class, 'index']);
// Deliberately outside auth:sanctum — the detail page is the public landing target for shared links (SHARE-003).
Route::get('/events/{id}', [EventController::class, 'show']);

// Deliberately outside auth:sanctum — filter picker options are anonymous, mirroring GET /api/events.
Route::get('/filter-options/genres', [FilterOptionsController::class, 'genres']);
Route::get('/filter-options/artists', [FilterOptionsController::class, 'artists']);
