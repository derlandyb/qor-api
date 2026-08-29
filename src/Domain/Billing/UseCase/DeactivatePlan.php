<?php

namespace QOR\App\Domain\Billing\UseCase;

use InvalidArgumentException;
use QOR\App\Domain\Billing\Plan;
use QOR\App\Domain\Billing\PlanRepository;

final class DeactivatePlan
{
    public function __construct(
        private readonly PlanRepository $plans,
    ) {
    }

    public function execute(int $planId): Plan
    {
        $plan = $this->plans->findById($planId);

        if ($plan === null) {
            throw new InvalidArgumentException('Plano não encontrado.');
        }

        return $this->plans->save(new Plan(
            id: $plan->id,
            name: $plan->name,
            monthlyPrice: $plan->monthlyPrice,
            annualPrice: $plan->annualPrice,
            publishQuota: $plan->publishQuota,
            isActive: false,
            isDefaultFree: $plan->isDefaultFree,
        ));
    }
}
