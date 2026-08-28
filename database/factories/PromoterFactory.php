<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\PromoterModel;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\QOR\App\Infrastructure\Persistence\Eloquent\PromoterModel>
 */
class PromoterFactory extends Factory
{
    protected $model = PromoterModel::class;

    public function definition(): array
    {
        return [
            'user_id' => AdminUserModel::factory(),
            'name' => fake()->name() . ' Produções',
            'contact_phone' => fake()->phoneNumber(),
            'contact_email' => fake()->unique()->companyEmail(),
            'instagram' => '@' . fake()->userName(),
            'tiktok' => '@' . fake()->userName(),
            'approval_status' => ApprovalStatus::PendingApproval->value,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_status' => ApprovalStatus::Approved->value,
        ]);
    }
}
