<?php

namespace QOR\App\Domain\Billing\UseCase;

use QOR\App\Domain\Billing\Plan;
use QOR\App\Domain\Billing\PlanRepository;

final class CreatePlan
{
    public function __construct(
        private readonly PlanRepository $plans,
    ) {
    }

    public function execute(
        string $name,
        float $monthlyPrice,
        ?int $publishQuota,
        ?float $annualPrice = null,
    ): Plan {
        return $this->plans->save(new Plan(
            id: null,
            name: $name,
            monthlyPrice: $monthlyPrice,
            annualPrice: $annualPrice,
            publishQuota: $publishQuota,
            isActive: true,
        ));
    }
}
