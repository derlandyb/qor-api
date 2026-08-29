<?php

namespace QOR\App\Domain\Billing\UseCase;

use DateTimeImmutable;
use InvalidArgumentException;
use QOR\App\Domain\Billing\Enum\SubscribableType;
use QOR\App\Domain\Billing\Enum\SubscriptionStatus;
use QOR\App\Domain\Billing\PlanRepository;
use QOR\App\Domain\Billing\Subscription;
use QOR\App\Domain\Billing\SubscriptionRepository;

final class CreateSubscriptionOnApproval
{
    public function __construct(
        private readonly PlanRepository $plans,
        private readonly SubscriptionRepository $subscriptions,
    ) {
    }

    public function execute(SubscribableType $subscribableType, int $subscribableId): Subscription
    {
        $defaultFreePlan = $this->plans->findDefaultFree();

        if ($defaultFreePlan === null) {
            throw new InvalidArgumentException('Configure um plano gratuito padrão antes de aprovar contas.');
        }

        $now = new DateTimeImmutable();
        $periodStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        $periodEnd = $now->modify('first day of next month')->setTime(0, 0, 0);

        return $this->subscriptions->save(new Subscription(
            id: null,
            subscribableType: $subscribableType,
            subscribableId: $subscribableId,
            planId: (int) $defaultFreePlan->id,
            status: SubscriptionStatus::Active,
            currentPeriodStart: $periodStart,
            currentPeriodEnd: $periodEnd,
            publishesUsedThisPeriod: 0,
        ));
    }
}
