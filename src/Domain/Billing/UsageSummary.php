<?php

namespace QOR\App\Domain\Billing;

final class UsageSummary
{
    public function __construct(
        public readonly string $planName,
        public readonly float $monthlyPrice,
        public readonly ?int $publishQuota,
        public readonly int $publishesUsedThisPeriod,
        public readonly bool $isAtLimit,
    ) {
    }
}
