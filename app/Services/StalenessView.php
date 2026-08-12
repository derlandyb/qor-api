<?php

namespace App\Services;

/**
 * Plain value object — never persisted, computed per-request and attached to a queue-list row
 * (ADMIN-005). `ageUnit` distinguishes what `ageValue`/`thresholdValue` are measured in, since
 * the two queues use different units (see QueueStalenessCalculator).
 */
final class StalenessView
{
    public function __construct(
        public readonly float $ageValue,       // hours (events) or business days (verification)
        public readonly string $ageUnit,       // 'hours' | 'business_days'
        public readonly bool $isStale,
        public readonly float $thresholdValue, // 48 or 5, for the Web client to render "X / 48h" style copy without hardcoding
    ) {}
}
