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

        return self::isFinished($event) ? BannerStatus::Finished : null;
    }

    /** Derived from the clock, not solely the persisted `finished` enum value — see resolve()'s docblock. */
    public static function isFinished(Event $event): bool
    {
        if ($event->status === EventStatus::Finished) {
            return true;
        }

        $effectiveEnd = $event->end_date_time ?? $event->start_date_time;

        return $effectiveEnd->isPast();
    }
}
