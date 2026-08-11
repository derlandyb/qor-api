<?php

namespace Database\Factories;

use App\Enums\VerificationStatus;
use App\Models\Promoter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promoter>
 */
class PromoterFactory extends Factory
{
    protected $model = Promoter::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'image_url' => fake()->imageUrl(),
            'description' => fake()->sentence(),
            'social_links' => ['instagram' => fake()->userName()],
            'contact_phone' => fake()->phoneNumber(),
            'contact_email' => fake()->companyEmail(),
            'verification_status' => VerificationStatus::Verified,
        ];
    }
}
