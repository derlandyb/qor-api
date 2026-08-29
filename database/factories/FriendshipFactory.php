<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use QOR\App\Domain\Social\Enum\FriendshipStatus;
use QOR\App\Infrastructure\Persistence\Eloquent\FriendshipModel;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\QOR\App\Infrastructure\Persistence\Eloquent\FriendshipModel>
 */
class FriendshipFactory extends Factory
{
    protected $model = FriendshipModel::class;

    public function definition(): array
    {
        return [
            'requester_id' => UserModel::factory(),
            'recipient_id' => UserModel::factory(),
            'status' => FriendshipStatus::Pending->value,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => ['status' => FriendshipStatus::Accepted->value]);
    }
}
