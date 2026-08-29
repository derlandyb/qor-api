<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\User\Enum\AddressSource;
use QOR\App\Infrastructure\Persistence\Eloquent\UserAddressModel;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\QOR\App\Infrastructure\Persistence\Eloquent\UserAddressModel>
 */
class UserAddressFactory extends Factory
{
    protected $model = UserAddressModel::class;

    public function definition(): array
    {
        /** @var City $city */
        $city = fake()->randomElement(City::cases());

        return [
            'user_id' => UserModel::factory(),
            'city' => $city->value,
            'state' => 'ES',
            'source' => AddressSource::Manual->value,
            'radius_km' => fake()->numberBetween(1, 20),
        ];
    }

    public function deviceLocation(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => AddressSource::DeviceLocation->value,
            'location_consent_given_at' => now(),
        ]);
    }
}
