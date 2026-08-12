<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\EventRevisionStatus;
use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The transaction event-publishing's design doc explicitly deferred to this feature: apply an
 * approved edit atomically onto the live Event row (or publish a new event outright), or flip
 * the pending item to Changes Requested with required feedback — while leaving an untouched
 * item's live, publicly-visible fields alone throughout review.
 */
final class EventReviewController extends Controller
{
    /** ADMIN-002. */
    public function approve(Event $event): JsonResponse
    {
        return DB::transaction(function () use ($event) {
            $event = Event::lockForUpdate()->findOrFail($event->id);
            $revision = EventRevision::where('event_id', $event->id)
                ->where('status', EventRevisionStatus::PendingApproval)->lockForUpdate()->first();

            abort_unless(
                $event->status === EventStatus::PendingApproval || $revision !== null,
                409,
                'Este item não está mais aguardando revisão.'
            );

            if ($revision) {
                $event->fill($revision->only([
                    'title', 'description', 'cover_image_url', 'start_date_time', 'end_date_time',
                    'venue_id', 'price_min', 'price_max', 'is_free', 'currency', 'age_rating',
                    'genres', 'ticket_url',
                ]));
                $revision->delete();
            }
            $event->update(['status' => EventStatus::Published, 'reviewer_feedback' => null]);

            return response()->json(['event' => $event->fresh()]);
        });
    }

    /**
     * Single backend action for both the "Rejeitar" and "Solicitar Alterações" Web labels — the
     * domain model has no standalone "Rejected" state for events, both outcomes land in
     * Changes Requested (see design.md's Tech Decisions). ADMIN-002.
     */
    public function requestChanges(Event $event, Request $request): JsonResponse
    {
        $feedback = $request->validate(['feedback' => 'required|string|min:1'])['feedback'];

        return DB::transaction(function () use ($event, $feedback) {
            $event = Event::lockForUpdate()->findOrFail($event->id);
            $revision = EventRevision::where('event_id', $event->id)
                ->where('status', EventRevisionStatus::PendingApproval)->lockForUpdate()->first();

            abort_unless(
                $event->status === EventStatus::PendingApproval || $revision !== null,
                409,
                'Este item não está mais aguardando revisão.'
            );

            if ($revision) {
                $revision->update(['status' => EventRevisionStatus::ChangesRequested, 'reviewer_feedback' => $feedback]);
            } else {
                $event->update(['status' => EventStatus::ChangesRequested, 'reviewer_feedback' => $feedback]);
            }

            return response()->json(['event' => $event->fresh()]);
        });
    }
}
