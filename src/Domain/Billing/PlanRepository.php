<?php

namespace QOR\App\Domain\Billing;

interface PlanRepository
{
    public function findById(int $id): ?Plan;

    /**
     * @return list<Plan>
     */
    public function findActive(): array;

    public function findDefaultFree(): ?Plan;

    public function save(Plan $plan): Plan;
}
