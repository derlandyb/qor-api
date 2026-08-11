<?php

namespace Database\Factories;

use App\Enums\VerificationStatus;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    protected $model = Venue::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'image_url' => fake()->imageUrl(),
            'description' => fake()->sentence(),
            'address' => fake()->streetAddress(),
            'city' => fake()->randomElement(['Vitória', 'Vila Velha', 'Serra', 'Cariacica']),
            'latitude' => fake()->latitude(-20.4, -20.2),
            'longitude' => fake()->longitude(-40.4, -40.2),
            'contact_phone' => fake()->phoneNumber(),
            'contact_email' => fake()->companyEmail(),
            'social_links' => ['instagram' => fake()->userName()],
            'verification_status' => VerificationStatus::Verified,
        ];
    }
}
