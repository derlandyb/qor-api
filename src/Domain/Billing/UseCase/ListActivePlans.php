<?php

namespace QOR\App\Domain\Billing\UseCase;

use QOR\App\Domain\Billing\Plan;
use QOR\App\Domain\Billing\PlanRepository;

final class ListActivePlans
{
    public function __construct(
        private readonly PlanRepository $plans,
    ) {
    }

    /**
     * @return list<Plan>
     */
    public function execute(): array
    {
        return $this->plans->findActive();
    }
}
