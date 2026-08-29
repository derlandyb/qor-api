<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use QOR\App\Infrastructure\Persistence\Eloquent\PlanModel;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<PlanModel>
 */
class PlanFactory extends Factory
{
    protected $model = PlanModel::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'monthly_price' => $this->faker->randomFloat(2, 0, 200),
            'annual_price' => null,
            'publish_quota' => 5,
            'is_active' => true,
            'is_default_free' => false,
        ];
    }
}
