<?php

namespace QOR\App\Domain\Billing\UseCase;

use InvalidArgumentException;
use QOR\App\Domain\Billing\Enum\SubscribableType;
use QOR\App\Domain\Billing\PlanRepository;
use QOR\App\Domain\Billing\Subscription;
use QOR\App\Domain\Billing\SubscriptionRepository;

final class CheckAndIncrementQuota
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly PlanRepository $plans,
    ) {
    }

    public function execute(SubscribableType $subscribableType, int $subscribableId): void
    {
        $subscription = $this->subscriptions->findBySubscribable($subscribableType, $subscribableId);

        if ($subscription === null) {
            throw new InvalidArgumentException('Nenhuma assinatura encontrada para esta conta.');
        }

        $plan = $this->plans->findById($subscription->planId);

        if ($plan === null) {
            throw new InvalidArgumentException('Plano da assinatura não encontrado.');
        }

        if ($plan->publishQuota !== null && $subscription->publishesUsedThisPeriod >= $plan->publishQuota) {
            throw new InvalidArgumentException('Você atingiu o limite de publicações do seu plano.');
        }

        $this->subscriptions->save(new Subscription(
            id: $subscription->id,
            subscribableType: $subscription->subscribableType,
            subscribableId: $subscription->subscribableId,
            planId: $subscription->planId,
            status: $subscription->status,
            currentPeriodStart: $subscription->currentPeriodStart,
            currentPeriodEnd: $subscription->currentPeriodEnd,
            publishesUsedThisPeriod: $subscription->publishesUsedThisPeriod + 1,
            billingCycle: $subscription->billingCycle,
        ));
    }
}
