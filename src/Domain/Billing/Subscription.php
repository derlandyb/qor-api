<?php

namespace QOR\App\Domain\Billing;

use DateTimeImmutable;
use QOR\App\Domain\Billing\Enum\BillingCycle;
use QOR\App\Domain\Billing\Enum\SubscribableType;
use QOR\App\Domain\Billing\Enum\SubscriptionStatus;

final class Subscription
{
    public function __construct(
        public readonly ?int $id,
        public readonly SubscribableType $subscribableType,
        public readonly int $subscribableId,
        public readonly int $planId,
        public readonly SubscriptionStatus $status,
        public readonly DateTimeImmutable $currentPeriodStart,
        public readonly DateTimeImmutable $currentPeriodEnd,
        public readonly int $publishesUsedThisPeriod = 0,
        public readonly ?BillingCycle $billingCycle = null,
    ) {
    }
}
