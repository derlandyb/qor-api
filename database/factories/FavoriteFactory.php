<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use QOR\App\Infrastructure\Persistence\Eloquent\EventModel;
use QOR\App\Infrastructure\Persistence\Eloquent\FavoriteModel;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\QOR\App\Infrastructure\Persistence\Eloquent\FavoriteModel>
 */
class FavoriteFactory extends Factory
{
    protected $model = FavoriteModel::class;

    public function definition(): array
    {
        return [
            'user_id' => UserModel::factory(),
            'event_id' => EventModel::factory(),
            'created_at' => now(),
        ];
    }
}
