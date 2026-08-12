<?php

namespace App\Services;

use Carbon\CarbonInterface;

/**
 * Monday–Friday only; excludes Saturday/Sunday, no Brazilian public-holiday calendar
 * (context.md doesn't ask for holiday-awareness, and the 5-business-day threshold itself
 * is provisional/unconfirmed — OPEN-05). Used as a duration unit ("a weekday"), not an
 * office-hours calendar, so a partial weekday contributes its own fractional share.
 */
final class BusinessDayCalculator
{
    private const SECONDS_PER_DAY = 86400;

    public function businessDaysBetween(CarbonInterface $from, CarbonInterface $to): float
    {
        if ($to->lessThanOrEqualTo($from)) {
            return 0.0;
        }

        $businessSeconds = 0;
        $cursor = $from->copy();

        while ($cursor->lessThan($to)) {
            $nextDayStart = $cursor->copy()->addDay()->startOfDay();
            $segmentEnd = $nextDayStart->greaterThan($to) ? $to : $nextDayStart;

            if (! $cursor->isWeekend()) {
                $businessSeconds += $cursor->diffInSeconds($segmentEnd);
            }

            $cursor = $segmentEnd;
        }

        return $businessSeconds / self::SECONDS_PER_DAY;
    }
}
