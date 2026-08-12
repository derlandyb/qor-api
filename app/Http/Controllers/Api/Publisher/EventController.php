<?php

namespace App\Http\Controllers\Api\Publisher;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Publisher\EventSubmitRequest;
use App\Http\Resources\Publisher\EventResource;
use App\Http\Resources\Publisher\EventRevisionResource;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The publisher-facing create/edit write surface (PUBLISH-001..003/008). Every route sits
 * inside auth:sanctum + can:manage-events (verification's gate, reused unmodified);
 * EventPolicy::manage() layers per-row ownership on top for show/update.
 */
final class EventController extends Controller
{
    public function store(EventSubmitRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();
        $submit = $validated['action'] === 'submit';

        $event = Event::submitForApproval($this->mappedFields($validated, $user), $submit);

        $this->syncOwnership($event, $user, $validated);

        return (new EventResource($event->fresh(['venue'])))->response()->setStatusCode(201);
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
        $fields = $this->mappedFields($validated, $user);

        if ($event->status === EventStatus::Published) {
            $event->submitEdit($fields);
        } else {
            $event->resubmit($fields, $validated['action'] === 'submit');
            $this->syncOwnership($event, $user, $validated);
        }

        return (new EventResource($event->fresh(['venue'])))->response();
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
    private function mappedFields(array $validated, User $user): array
    {
        $venueId = $user->venueProfile !== null
            ? $user->venueProfile->id
            : ($validated['venueId'] ?? null);

        return [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'cover_image_url' => $validated['coverImageUrl'] ?? null,
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
}
