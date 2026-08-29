<?php

namespace QOR\App\Infrastructure\Persistence;

use QOR\App\Domain\Billing\Plan;
use QOR\App\Domain\Billing\PlanRepository;
use QOR\App\Infrastructure\Persistence\Eloquent\PlanModel;

class EloquentPlanRepository implements PlanRepository
{
    public function findById(int $id): ?Plan
    {
        $model = PlanModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findActive(): array
    {
        /** @var list<Plan> $plans */
        $plans = PlanModel::where('is_active', true)
            ->get()
            ->map(fn (PlanModel $model): Plan => $this->toDomain($model))
            ->values()
            ->all();

        return $plans;
    }

    public function findAll(): array
    {
        /** @var list<Plan> $plans */
        $plans = PlanModel::orderBy('id')
            ->get()
            ->map(fn (PlanModel $model): Plan => $this->toDomain($model))
            ->values()
            ->all();

        return $plans;
    }

    public function findDefaultFree(): ?Plan
    {
        $model = PlanModel::where('is_default_free', true)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(Plan $plan): Plan
    {
        $model = $plan->id !== null ? PlanModel::findOrFail($plan->id) : new PlanModel();

        $model->fill([
            'name' => $plan->name,
            'monthly_price' => $plan->monthlyPrice,
            'annual_price' => $plan->annualPrice,
            'publish_quota' => $plan->publishQuota,
            'is_active' => $plan->isActive,
            'is_default_free' => $plan->isDefaultFree,
        ]);

        $model->save();

        return $this->toDomain($model);
    }

    private function toDomain(PlanModel $model): Plan
    {
        return new Plan(
            id: $model->id,
            name: $model->name,
            monthlyPrice: (float) $model->monthly_price,
            annualPrice: $model->annual_price !== null ? (float) $model->annual_price : null,
            publishQuota: $model->publish_quota !== null ? (int) $model->publish_quota : null,
            isActive: (bool) $model->is_active,
            isDefaultFree: (bool) $model->is_default_free,
        );
    }
}
