<?php

use App\Http\Controllers\Api\Admin\VerificationReviewController;
use App\Http\Controllers\Api\Admin\VerificationRevocationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\FilterOptionsController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\MapController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\Publisher\EventController as PublisherEventController;
use App\Http\Controllers\Api\VerificationApplicationController;
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

// Anonymous — registration/login precede any session, mirroring GET /api/events.
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

// FAVORITE-006/007 — the one part of this feature's surface that requires auth.
Route::middleware('auth:sanctum')->group(function () {
    Route::put('/favorites/{event}', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{event}', [FavoriteController::class, 'destroy']);
    Route::get('/favorites', [FavoriteController::class, 'index']);
});

// VERIFY-002/003/004/007 — applicant-facing submission/status, gated behind auth like favorites.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/verification/applications', [VerificationApplicationController::class, 'store']);
    Route::get('/verification/status', [VerificationApplicationController::class, 'status']);
});

// VERIFY-001/005/006 — admin review/revocation. Deliberately auth:sanctum only, no role check yet:
// the concrete `admin` role doesn't exist in this codebase until `moderation`, which retroactively
// gates these exact routes — a known, designed sequencing gap, not an oversight.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/admin/verification-applications/{application}/approve', [VerificationReviewController::class, 'approve']);
    Route::post('/admin/verification-applications/{application}/reject', [VerificationReviewController::class, 'reject']);
    Route::get('/admin/verification-applications', [VerificationReviewController::class, 'index']);
    Route::post('/admin/promoters/{promoter}/revoke', [VerificationRevocationController::class, 'revokePromoter']);
    Route::post('/admin/venues/{venue}/revoke', [VerificationRevocationController::class, 'revokeVenue']);
});

// PUBLISH-001/002/003/008 — publisher-facing create/edit write surface. `manage-events` is
// verification's Gate, reused unmodified; EventPolicy::manage() layers per-row ownership on
// top for show/update (route-model-bound, so a nonexistent id still 404s beforehand).
Route::middleware(['auth:sanctum', 'can:manage-events'])->prefix('publisher')->group(function () {
    Route::post('/events', [PublisherEventController::class, 'store']);
    Route::get('/events/{event}', [PublisherEventController::class, 'show']);
    Route::patch('/events/{event}', [PublisherEventController::class, 'update']);
});

Route::post('/auth/google', [GoogleAuthController::class, 'login']);
Route::post('/password/forgot', [PasswordResetController::class, 'forgot']);
// Named password.reset — Laravel's default ResetPassword notification builds its email link via
// route('password.reset', ...), which throws RouteNotFoundException if the name isn't registered.
// pt-BR copy/template customization is deferred (see PR description); this keeps the default
// notification working as-is until that's addressed.
Route::post('/password/reset', [PasswordResetController::class, 'reset'])->name('password.reset');

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
