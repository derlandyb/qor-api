<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Enums\VerificationApplicationStatus;
use App\Models\Promoter;
use App\Models\User;
use App\Models\VerificationApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VerificationApplication>
 */
class VerificationApplicationFactory extends Factory
{
    protected $model = VerificationApplication::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_type' => AccountType::Promoter,
            'promoter_id' => Promoter::factory(),
            'venue_id' => null,
            'business_name' => fake()->company(),
            'contact_email' => fake()->companyEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'social_link' => 'https://instagram.com/'.fake()->userName(),
            'document_path' => null,
            'status' => VerificationApplicationStatus::PendingReview,
        ];
    }
}
