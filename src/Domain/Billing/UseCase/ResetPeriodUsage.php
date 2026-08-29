<?php

namespace QOR\App\Domain\Billing\UseCase;

use DateTimeImmutable;
use QOR\App\Domain\Billing\Subscription;
use QOR\App\Domain\Billing\SubscriptionRepository;

final class ResetPeriodUsage
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
    ) {
    }

    public function execute(): void
    {
        $now = new DateTimeImmutable();
        $periodStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        $periodEnd = $now->modify('first day of next month')->setTime(0, 0, 0);

        foreach ($this->subscriptions->all() as $subscription) {
            $this->subscriptions->save(new Subscription(
                id: $subscription->id,
                subscribableType: $subscription->subscribableType,
                subscribableId: $subscription->subscribableId,
                planId: $subscription->planId,
                status: $subscription->status,
                currentPeriodStart: $periodStart,
                currentPeriodEnd: $periodEnd,
                publishesUsedThisPeriod: 0,
                billingCycle: $subscription->billingCycle,
            ));
        }
    }
}
