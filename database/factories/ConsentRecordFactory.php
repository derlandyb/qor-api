<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use QOR\App\Domain\User\Enum\ConsentableType;
use QOR\App\Domain\User\Enum\ConsentType;
use QOR\App\Infrastructure\Persistence\Eloquent\ConsentRecordModel;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\QOR\App\Infrastructure\Persistence\Eloquent\ConsentRecordModel>
 */
class ConsentRecordFactory extends Factory
{
    protected $model = ConsentRecordModel::class;

    public function definition(): array
    {
        return [
            'consentable_type' => ConsentableType::User->value,
            'consentable_id' => UserModel::factory(),
            'consent_type' => ConsentType::Terms->value,
            'policy_version' => '1.0',
            'accepted_at' => now(),
        ];
    }
}
