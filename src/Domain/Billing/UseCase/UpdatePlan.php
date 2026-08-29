<?php

namespace QOR\App\Domain\Billing\UseCase;

use InvalidArgumentException;
use QOR\App\Domain\Billing\Plan;
use QOR\App\Domain\Billing\PlanRepository;

final class UpdatePlan
{
    public function __construct(
        private readonly PlanRepository $plans,
    ) {
    }

    public function execute(
        int $planId,
        string $name,
        float $monthlyPrice,
        ?int $publishQuota,
        ?float $annualPrice = null,
    ): Plan {
        $plan = $this->plans->findById($planId);

        if ($plan === null) {
            throw new InvalidArgumentException('Plano não encontrado.');
        }

        return $this->plans->save(new Plan(
            id: $plan->id,
            name: $name,
            monthlyPrice: $monthlyPrice,
            annualPrice: $annualPrice,
            publishQuota: $publishQuota,
            isActive: $plan->isActive,
            isDefaultFree: $plan->isDefaultFree,
        ));
    }
}
