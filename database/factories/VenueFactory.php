<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\VenueModel;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\QOR\App\Infrastructure\Persistence\Eloquent\VenueModel>
 */
class VenueFactory extends Factory
{
    protected $model = VenueModel::class;

    public function definition(): array
    {
        /** @var City $city */
        $city = fake()->randomElement(City::cases());

        return [
            'venue_admin_user_id' => AdminUserModel::factory(),
            'name' => fake()->company(),
            'description' => fake()->paragraph(),
            'address' => fake()->streetAddress(),
            'city' => $city->value,
            'contact_phone' => fake()->phoneNumber(),
            'contact_email' => fake()->unique()->companyEmail(),
            'image_url' => fake()->imageUrl(),
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
