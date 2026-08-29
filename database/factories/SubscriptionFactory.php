<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use QOR\App\Domain\Billing\Enum\SubscribableType;
use QOR\App\Domain\Billing\Enum\SubscriptionStatus;
use QOR\App\Infrastructure\Persistence\Eloquent\PlanModel;
use QOR\App\Infrastructure\Persistence\Eloquent\SubscriptionModel;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<SubscriptionModel>
 */
class SubscriptionFactory extends Factory
{
    protected $model = SubscriptionModel::class;

    public function definition(): array
    {
        return [
            'subscribable_type' => SubscribableType::Venue->value,
            'subscribable_id' => 1,
            'plan_id' => PlanModel::factory(),
            'status' => SubscriptionStatus::Active->value,
            'billing_cycle' => null,
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
            'publishes_used_this_period' => 0,
        ];
    }
}
