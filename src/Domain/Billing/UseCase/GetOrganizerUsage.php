<?php

namespace QOR\App\Domain\Billing\UseCase;

use InvalidArgumentException;
use QOR\App\Domain\Billing\Enum\SubscribableType;
use QOR\App\Domain\Billing\PlanRepository;
use QOR\App\Domain\Billing\SubscriptionRepository;
use QOR\App\Domain\Billing\UsageSummary;

final class GetOrganizerUsage
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly PlanRepository $plans,
    ) {
    }

    public function execute(SubscribableType $subscribableType, int $subscribableId): UsageSummary
    {
        $subscription = $this->subscriptions->findBySubscribable($subscribableType, $subscribableId);

        if ($subscription === null) {
            throw new InvalidArgumentException('Nenhuma assinatura encontrada para esta conta.');
        }

        $plan = $this->plans->findById($subscription->planId);

        if ($plan === null) {
            throw new InvalidArgumentException('Plano da assinatura não encontrado.');
        }

        $isAtLimit = $plan->publishQuota !== null
            && $subscription->publishesUsedThisPeriod >= $plan->publishQuota;

        return new UsageSummary(
            planName: $plan->name,
            monthlyPrice: $plan->monthlyPrice,
            publishQuota: $plan->publishQuota,
            publishesUsedThisPeriod: $subscription->publishesUsedThisPeriod,
            isAtLimit: $isAtLimit,
        );
    }
}
