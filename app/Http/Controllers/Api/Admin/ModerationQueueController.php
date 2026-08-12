<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\EventRevisionStatus;
use App\Enums\EventStatus;
use App\Enums\VerificationApplicationStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PromoterResource;
use App\Http\Resources\Publisher\EventResource;
use App\Http\Resources\Publisher\EventRevisionResource;
use App\Http\Resources\VenueResource;
use App\Http\Resources\VerificationApplicationResource;
use App\Models\Event;
use App\Models\EventRevision;
use App\Models\Promoter;
use App\Models\Venue;
use App\Models\VerificationApplication;
use App\Services\QueueStalenessCalculator;
use App\Services\StalenessView;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * The read side of both moderation queues, each row annotated with staleness (ADMIN-005) —
 * plus a small verified-accounts listing so VerifiedAccountsPage has something independent
 * of either queue to query (ADMIN-004).
 */
final class ModerationQueueController extends Controller
{
    /**
     * The exact field-copy list EventReviewController::approve() applies onto Event — reused
     * here so the event-queue "edit" rows' diff covers precisely what approval would change.
     */
    private const DIFFABLE_FIELDS = [
        'title', 'description', 'cover_image_url', 'start_date_time', 'end_date_time',
        'venue_id', 'price_min', 'price_max', 'is_free', 'currency', 'age_rating', 'genres', 'ticket_url',
    ];

    public function __construct(private readonly QueueStalenessCalculator $stalenessCalculator) {}

    /** ADMIN-001, ADMIN-005. */
    public function verificationQueue(): JsonResponse
    {
        $applications = VerificationApplication::query()
            ->where('status', VerificationApplicationStatus::PendingReview)
            ->orderBy('created_at')
            ->get();

        $items = $applications->map(fn (VerificationApplication $application) => [
            'application' => new VerificationApplicationResource($application),
            'staleness' => $this->stalenessCalculator->forVerificationApplication($application),
        ]);

        return response()->json([
            'data' => $items->values(),
            'queueStaleness' => $this->oldest($items->pluck('staleness')->all()),
        ]);
    }

    /** ADMIN-002, ADMIN-005. */
    public function eventQueue(): JsonResponse
    {
        // Never yet Published, so event-publishing's design guarantees no pendingRevision can
        // coexist with this status.
        $newSubmissions = Event::query()
            ->where('status', EventStatus::PendingApproval)
            ->with('venue')
            ->get()
            ->map(fn (Event $event) => $this->newSubmissionItem($event));

        $edits = Event::query()
            ->where('status', EventStatus::Published)
            ->whereHas('pendingRevision', fn (Builder $q) => $q->where('status', EventRevisionStatus::PendingApproval))
            ->with(['venue', 'pendingRevision'])
            ->get()
            ->map(fn (Event $event) => $this->editItem($event));

        $items = $newSubmissions->concat($edits);

        return response()->json([
            'data' => $items->values(),
            'queueStaleness' => $this->oldest($items->pluck('staleness')->all()),
        ]);
    }

    /** ADMIN-004 — feeds VerifiedAccountsPage, independent of either queue. */
    public function verifiedAccounts(): JsonResponse
    {
        $promoters = Promoter::query()
            ->where('verification_status', VerificationStatus::Verified)
            ->with('user')
            ->get()
            ->map(fn (Promoter $promoter) => [
                'entityType' => 'promoter',
                'promoter' => new PromoterResource($promoter),
                'ownerEmail' => $promoter->user?->email,
            ]);

        $venues = Venue::query()
            ->where('verification_status', VerificationStatus::Verified)
            ->with('user')
            ->get()
            ->map(fn (Venue $venue) => [
                'entityType' => 'venue',
                'venue' => new VenueResource($venue),
                'ownerEmail' => $venue->user?->email,
            ]);

        return response()->json(['data' => $promoters->concat($venues)->values()]);
    }

    private function newSubmissionItem(Event $event): array
    {
        return [
            'type' => 'new',
            'event' => new EventResource($event),
            'revision' => null,
            'diff' => null,
            'staleness' => $this->stalenessCalculator->forEventQueueItem($event->updated_at),
        ];
    }

    private function editItem(Event $event): array
    {
        /** @var EventRevision $revision */
        $revision = $event->pendingRevision;

        return [
            'type' => 'edit',
            'event' => new EventResource($event),
            'revision' => new EventRevisionResource($revision),
            'diff' => $this->diff($event, $revision),
            'staleness' => $this->stalenessCalculator->forEventQueueItem($revision->updated_at),
        ];
    }

    /**
     * Field-by-field comparison of Event's live values against the revision's snapshot values
     * (ADMIN-002 AC1) — only changed fields are included, so the admin isn't re-reviewing
     * unchanged fields blind.
     *
     * @return array<int, array{field: string, before: mixed, after: mixed}>
     */
    private function diff(Event $event, EventRevision $revision): array
    {
        $rows = [];

        foreach (self::DIFFABLE_FIELDS as $field) {
            $before = $this->comparable($event->{$field});
            $after = $this->comparable($revision->{$field});

            if ($before !== $after) {
                $rows[] = ['field' => $field, 'before' => $before, 'after' => $after];
            }
        }

        return $rows;
    }

    private function comparable(mixed $value): mixed
    {
        return match (true) {
            $value instanceof CarbonInterface => $value->toIso8601String(),
            $value instanceof BackedEnum => $value->value,
            default => $value,
        };
    }

    /**
     * `queueStaleness` reflects the oldest item in the list (largest age), `null` when the
     * queue is empty — rather than a fabricated zero (see Error Handling Strategy).
     *
     * @param  array<int, StalenessView>  $stalenessViews
     */
    private function oldest(array $stalenessViews): ?StalenessView
    {
        if ($stalenessViews === []) {
            return null;
        }

        usort($stalenessViews, fn (StalenessView $a, StalenessView $b) => $b->ageValue <=> $a->ageValue);

        return $stalenessViews[0];
    }
}
