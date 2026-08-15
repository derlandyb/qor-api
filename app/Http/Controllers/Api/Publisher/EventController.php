<?php

namespace App\Http\Controllers\Api\Publisher;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Publisher\EventSubmitRequest;
use App\Http\Resources\Publisher\EventResource;
use App\Http\Resources\Publisher\EventRevisionResource;
use App\Models\Event;
use App\Models\User;
use App\Services\EventDashboardStatusResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The full publisher-facing write surface plus the status-dashboard read (PUBLISH-001..008).
 * Every route sits inside auth:sanctum + can:manage-events (verification's gate, reused unmodified);
 * EventPolicy::manage() layers per-row ownership on top for show/update/cancel.
 */
final class EventController extends Controller
{
    public function store(EventSubmitRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();
        $submit = $validated['action'] === 'submit';

        $coverImagePath = $this->resolveCoverImagePath($request, null);
        if ($coverImagePath instanceof JsonResponse) {
            return $coverImagePath;
        }

        $event = Event::submitForApproval($this->mappedFields($validated, $user, $coverImagePath), $submit);

        $this->syncOwnership($event, $user, $validated);

        return (new EventResource($event->fresh(['venue'])))->response()->setStatusCode(201);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $items = Event::query()
            ->ownedBy($user)
            ->with(['venue', 'pendingRevision', 'promoters'])
            ->get()
            ->map(fn (Event $event) => [
                'event' => new EventResource($event),
                'dashboardStatus' => $this->dashboardStatusArray($event),
            ]);

        $statusFilter = $request->query('status');
        if ($statusFilter !== null) {
            $items = $items->filter(fn (array $item) => $item['dashboardStatus']['label'] === $statusFilter)->values();
        }

        return response()->json(['data' => $items]);
    }

    public function show(Request $request, Event $event): JsonResponse
    {
        $this->authorize('manage', $event);
        $event->load(['venue', 'pendingRevision']);

        return response()->json([
            'event' => new EventResource($event),
            'pendingRevision' => $event->pendingRevision ? new EventRevisionResource($event->pendingRevision) : null,
        ]);
    }

    public function update(EventSubmitRequest $request, Event $event): JsonResponse
    {
        $this->authorize('manage', $event);
        abort_if($event->status === EventStatus::Cancelled, 409, 'Este evento já foi cancelado.');

        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $coverImagePath = $this->resolveCoverImagePath($request, $event);
        if ($coverImagePath instanceof JsonResponse) {
            return $coverImagePath;
        }

        $fields = $this->mappedFields($validated, $user, $coverImagePath);

        if ($event->status === EventStatus::Published) {
            $event->submitEdit($fields);
        } else {
            $event->resubmit($fields, $validated['action'] === 'submit');
            $this->syncOwnership($event, $user, $validated);
        }

        return (new EventResource($event->fresh(['venue'])))->response();
    }

    public function cancel(Request $request, Event $event): JsonResponse
    {
        $this->authorize('manage', $event);

        $event->cancel();

        return (new EventResource($event->fresh(['venue'])))->response();
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardStatusArray(Event $event): array
    {
        $view = EventDashboardStatusResolver::resolve($event);

        return [
            'label' => $view->label->value,
            'reviewerFeedback' => $view->reviewerFeedback,
            'hasPendingEdit' => $view->hasPendingEdit,
            'pendingEditStatus' => $view->pendingEditStatus?->value,
        ];
    }

    /**
     * Maps the request's camelCase, API-shaped payload onto Event/EventRevision's snake_case
     * fillable columns. A Venue-profile account's `venueId` is always overridden server-side to
     * their own row, regardless of the client payload (defense in depth — see design.md's
     * Error Handling Strategy). Keyed off promoterProfile/venueProfile presence, matching the
     * manage-events Gate and EventPolicy — User.account_type is never actually populated
     * anywhere in this codebase (not at registration, not at verification approval).
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function mappedFields(array $validated, User $user, ?string $coverImagePath): array
    {
        $venueId = $user->venueProfile !== null
            ? $user->venueProfile->id
            : ($validated['venueId'] ?? null);

        return [
            'title' => $validated['title'],
            'description' => isset($validated['description']) ? strip_tags($validated['description']) : null,
            'cover_image_path' => $coverImagePath,
            'start_date_time' => $validated['startDateTime'] ?? null,
            'end_date_time' => $validated['endDateTime'] ?? null,
            'venue_id' => $venueId,
            'price_min' => $validated['priceMin'] ?? null,
            'price_max' => $validated['priceMax'] ?? null,
            'is_free' => $validated['isFree'] ?? false,
            'currency' => $validated['currency'] ?? 'BRL',
            'age_rating' => $validated['ageRating'] ?? null,
            'genres' => $validated['genres'] ?? null,
            'ticket_url' => $validated['ticketUrl'] ?? null,
        ];
    }

    /**
     * Attaches a Promoter-profile account's own Promoter row (never user-selectable, auto-attached)
     * and syncs the artist selection — both outside Event's own fillable columns, so applied
     * after the row itself is created/updated. Only touches artists when the client actually sent
     * `artistIds`, so a partial draft save never silently clears a prior selection.
     *
     * @param  array<string, mixed>  $validated
     */
    private function syncOwnership(Event $event, User $user, array $validated): void
    {
        if ($user->promoterProfile !== null) {
            $event->promoters()->syncWithoutDetaching([$user->promoterProfile->id]);
        }

        if (array_key_exists('artistIds', $validated) && $validated['artistIds'] !== null) {
            $event->artists()->sync($validated['artistIds']);
        }
    }

    /**
     * Resolves the stored S3 key for `cover_image_path`, run before any Event/EventRevision
     * write so an upload failure never leaves a partial record behind. A newly-uploaded file is
     * stored and its key returned; otherwise the live `$event`'s current path is carried forward
     * unchanged (`null` for a brand-new event) — omitting the field in FormData must never be
     * read as "clear the cover image." Mirrors VerificationApplicationController's upload
     * try/catch, but — unlike that best-effort document upload — a failure here blocks the
     * write and surfaces a structured error, since the cover image is a required, user-visible
     * submission field rather than an optional supporting document.
     */
    private function resolveCoverImagePath(Request $request, ?Event $event): string|JsonResponse|null
    {
        if (! $request->hasFile('coverImage')) {
            return $event?->cover_image_path;
        }

        try {
            // The s3 disk is configured with `throw` => false, so a write failure surfaces as a
            // `false` return rather than an exception in normal operation — the catch block below
            // only covers the narrower set of failures (e.g. a bad stream) that escape that guard.
            $path = Storage::disk('s3')->putFile('events/covers', $request->file('coverImage'));
        } catch (Throwable $e) {
            Log::error('Event cover image upload failed', ['exception' => $e]);

            return response()->json([
                'message' => 'Não foi possível enviar a imagem de capa. Tente novamente.',
            ], 502);
        }

        if ($path === false) {
            Log::error('Event cover image upload failed');

            return response()->json([
                'message' => 'Não foi possível enviar a imagem de capa. Tente novamente.',
            ], 502);
        }

        return $path;
    }
}
