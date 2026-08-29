<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use QOR\App\Infrastructure\Persistence\Eloquent\NotificationPreferenceModel;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\QOR\App\Infrastructure\Persistence\Eloquent\NotificationPreferenceModel>
 */
class NotificationPreferenceFactory extends Factory
{
    protected $model = NotificationPreferenceModel::class;

    public function definition(): array
    {
        return [
            'user_id' => UserModel::factory(),
            'push_enabled' => true,
            'email_enabled' => true,
            'silence_all' => false,
            'trigger_nearby_reminder' => true,
            'trigger_event_changed_cancelled' => true,
            'trigger_friend_interest' => true,
            'trigger_new_regional' => true,
        ];
    }
}
