<?php

namespace App\Services;

use App\Models\VerificationApplication;
use Carbon\CarbonInterface;

/**
 * The single computation point both queue endpoints call, so the 48h/5-business-day rule
 * from context.md is expressed exactly once. Staleness anchor timestamp differs per queue:
 * `VerificationApplication` rows are immutable once created (VERIFY-007 creates a fresh row
 * per reapplication), so `created_at` is exactly "when it entered the queue." `Event`/
 * `EventRevision` rows can be resubmitted while still pending, so the caller passes the
 * `updated_at` of whichever row is currently awaiting review.
 */
final class QueueStalenessCalculator
{
    private const EVENT_THRESHOLD_HOURS = 48.0;

    private const VERIFICATION_THRESHOLD_BUSINESS_DAYS = 5.0;

    public function __construct(private readonly BusinessDayCalculator $businessDayCalculator) {}

    public function forVerificationApplication(VerificationApplication $application): StalenessView
    {
        $ageValue = $this->businessDayCalculator->businessDaysBetween($application->created_at, now());

        return new StalenessView(
            ageValue: $ageValue,
            ageUnit: 'business_days',
            isStale: $ageValue > self::VERIFICATION_THRESHOLD_BUSINESS_DAYS,
            thresholdValue: self::VERIFICATION_THRESHOLD_BUSINESS_DAYS,
        );
    }

    public function forEventQueueItem(CarbonInterface $enteredPendingAt): StalenessView
    {
        $ageHours = $enteredPendingAt->diffInSeconds(now()) / 3600;

        return new StalenessView(
            ageValue: $ageHours,
            ageUnit: 'hours',
            isStale: $ageHours > self::EVENT_THRESHOLD_HOURS,
            thresholdValue: self::EVENT_THRESHOLD_HOURS,
        );
    }
}
