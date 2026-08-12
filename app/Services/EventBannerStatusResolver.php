<?php

namespace App\Services;

use App\Enums\BannerStatus;
use App\Enums\EventStatus;
use App\Models\Event;

final class EventBannerStatusResolver
{
    /**
     * Cancelled always wins over Finished, even for a past-dated cancelled event
     * (DETAIL-007/008 AC3 precedence rule). Finished is derived from the clock,
     * not solely from the persisted `finished` enum value, so old shared links
     * still show correctly before any status-transition job exists.
     */
    public static function resolve(Event $event): ?BannerStatus
    {
        if ($event->status === EventStatus::Cancelled) {
            return BannerStatus::Cancelled;
        }

        $effectiveEnd = $event->end_date_time ?? $event->start_date_time;

        if ($event->status === EventStatus::Finished || $effectiveEnd->isPast()) {
            return BannerStatus::Finished;
        }

        return null;
    }
}
