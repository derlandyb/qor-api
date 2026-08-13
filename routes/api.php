<?php

use App\Http\Controllers\Api\Admin\EventReviewController;
use App\Http\Controllers\Api\Admin\ModerationQueueController;
use App\Http\Controllers\Api\Admin\PasswordChangeController;
use App\Http\Controllers\Api\Admin\StaffController;
use App\Http\Controllers\Api\Admin\VerificationReviewController;
use App\Http\Controllers\Api\Admin\VerificationRevocationController;
use App\Http\Controllers\Api\AnalyticsEventController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\FilterOptionsController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\MapController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\Publisher\EventController as PublisherEventController;
use App\Http\Controllers\Api\Publisher\VenueOptionController;
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

// admin-auth (ADMIN-AUTH-002) — pure route aliases onto the same AuthController/
// PasswordResetController methods above; every route qor-admin calls must live under
// /api/admin/*, per ARCHITECTURE.md's "/api/admin/* vs consumer /api/*" boundary convention.
Route::post('/admin/register', [AuthController::class, 'register']);
Route::post('/admin/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('/admin/logout', [AuthController::class, 'logout']);
Route::post('/admin/password/forgot', [PasswordResetController::class, 'forgot']);
Route::post('/admin/password/reset', [PasswordResetController::class, 'reset']);

// ADMIN-AUTH-003 — any authenticated role; the one route that works regardless of
// must_change_password, since it's how the flag itself gets cleared.
Route::middleware('auth:sanctum')->post('/admin/change-password', [PasswordChangeController::class, 'store']);

// ADMIN-AUTH-004 — Super-Admin-exclusive staff-account management, no self-service path.
Route::middleware(['auth:sanctum', 'can:super-admin'])->group(function () {
    Route::post('/admin/staff', [StaffController::class, 'store']);
    Route::get('/admin/staff', [StaffController::class, 'index']);
});

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

// VERIFY-001/005/006 — admin review/revocation. Previously auth:sanctum only, no role check —
// the concrete `admin` role didn't exist in this codebase until `moderation`, which retroactively
// gates these exact routes with `can:admin` below, a known, designed sequencing gap now closed.
Route::middleware(['auth:sanctum', 'can:admin'])->group(function () {
    Route::post('/admin/verification-applications/{application}/approve', [VerificationReviewController::class, 'approve']);
    Route::post('/admin/verification-applications/{application}/reject', [VerificationReviewController::class, 'reject']);
    Route::get('/admin/verification-applications', [VerificationReviewController::class, 'index']);
    Route::post('/admin/promoters/{promoter}/revoke', [VerificationRevocationController::class, 'revokePromoter']);
    Route::post('/admin/venues/{venue}/revoke', [VerificationRevocationController::class, 'revokeVenue']);
});

// ADMIN-001/002/004/005 — moderator-only queues plus the new Event approve/request-changes
// actions event-publishing's design explicitly deferred to this feature.
Route::middleware(['auth:sanctum', 'can:admin'])->group(function () {
    Route::get('/admin/moderation/verification-queue', [ModerationQueueController::class, 'verificationQueue']);
    Route::get('/admin/moderation/event-queue', [ModerationQueueController::class, 'eventQueue']);
    Route::get('/admin/moderation/verified-accounts', [ModerationQueueController::class, 'verifiedAccounts']);
    Route::post('/admin/events/{event}/approve', [EventReviewController::class, 'approve']);
    Route::post('/admin/events/{event}/request-changes', [EventReviewController::class, 'requestChanges']);
});

// PUBLISH-001..008 — publisher-facing write surface + status dashboard. `manage-events` is
// verification's Gate, reused unmodified; EventPolicy::manage() layers per-row ownership on
// top for show/update/cancel (route-model-bound, so a nonexistent id still 404s beforehand).
Route::middleware(['auth:sanctum', 'can:manage-events'])->prefix('publisher')->group(function () {
    Route::post('/events', [PublisherEventController::class, 'store']);
    Route::get('/events', [PublisherEventController::class, 'index']);
    Route::get('/events/{event}', [PublisherEventController::class, 'show']);
    Route::patch('/events/{event}', [PublisherEventController::class, 'update']);
    Route::post('/events/{event}/cancel', [PublisherEventController::class, 'cancel']);
    Route::get('/venues', [VenueOptionController::class, 'index']);
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

// Deliberately outside auth:sanctum — an anonymous visitor's events (event_viewed, search_performed,
// etc.) must still be recordable; optional Sanctum resolution attributes user_id when a token is present.
Route::post('/analytics/events', [AnalyticsEventController::class, 'store']);
