<?php

namespace App\Services;

use App\Enums\BannerStatus;
use App\Enums\EventStatus;
use App\Models\Event;

/**
 * Folds Event.status, event-details' Cancelled-over-Finished precedence, and an event's
 * optional in-flight EventRevision into one status view for the publisher dashboard.
 * Reuses EventBannerStatusResolver directly rather than re-deriving that precedence a third time.
 */
final class EventDashboardStatusResolver
{
    public static function resolve(Event $event): DashboardStatusView
    {
        // First-time submission, never yet Published: Event.status is the whole story.
        if (in_array($event->status, [EventStatus::Draft, EventStatus::PendingApproval, EventStatus::ChangesRequested], true)) {
            return new DashboardStatusView($event->status, $event->reviewer_feedback, hasPendingEdit: false);
        }

        // Published or Cancelled: reuse event-details' precedence for the Finished overlay.
        $banner = EventBannerStatusResolver::resolve($event); // null | Cancelled | Finished
        $label = match ($banner) {
            BannerStatus::Cancelled => EventStatus::Cancelled,
            BannerStatus::Finished => EventStatus::Finished,
            null => EventStatus::Published,
        };

        $revision = $event->pendingRevision;

        return new DashboardStatusView(
            $label,
            $revision?->reviewer_feedback,
            hasPendingEdit: $revision !== null,
            pendingEditStatus: $revision?->status,
        );
    }
}
