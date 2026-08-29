<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use QOR\App\Infrastructure\Persistence\Eloquent\NotificationPreferenceModel;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\TestCase;

class NotificationPreferenceControllerTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(UserModel $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('mobile')->plainTextToken];
    }

    public function test_GIVEN_no_token_WHEN_viewing_preferences_THEN_it_is_rejected(): void
    {
        $this->getJson('/api/v1/profile/notification-preferences')->assertStatus(401);
    }

    public function test_GIVEN_no_preference_row_yet_WHEN_viewing_preferences_THEN_defaults_are_returned(): void
    {
        $user = UserModel::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/profile/notification-preferences');

        $response->assertStatus(200)
            ->assertJsonPath('data.push_enabled', true)
            ->assertJsonPath('data.silence_all', false);
    }

    public function test_GIVEN_an_existing_preference_row_WHEN_viewing_preferences_THEN_it_is_returned(): void
    {
        $user = UserModel::factory()->create();
        NotificationPreferenceModel::factory()->create([
            'user_id' => $user->id,
            'silence_all' => true,
        ]);

        $response = $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/profile/notification-preferences');

        $response->assertStatus(200)->assertJsonPath('data.silence_all', true);
    }

    public function test_GIVEN_disabling_one_trigger_WHEN_updating_preferences_THEN_others_stay_unaffected(): void
    {
        $user = UserModel::factory()->create();
        NotificationPreferenceModel::factory()->create(['user_id' => $user->id]);

        $response = $this->withHeaders($this->authHeader($user))
            ->patchJson('/api/v1/profile/notification-preferences', ['trigger_friend_interest' => false]);

        $response->assertStatus(200)
            ->assertJsonPath('data.trigger_friend_interest', false)
            ->assertJsonPath('data.trigger_nearby_reminder', true)
            ->assertJsonPath('data.trigger_new_regional', true);
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'trigger_friend_interest' => false,
            'trigger_nearby_reminder' => true,
        ]);
    }

    public function test_GIVEN_no_preference_row_yet_WHEN_updating_preferences_THEN_it_is_created(): void
    {
        $user = UserModel::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->patchJson('/api/v1/profile/notification-preferences', ['silence_all' => true]);

        $response->assertStatus(200)->assertJsonPath('data.silence_all', true);
        $this->assertDatabaseHas('notification_preferences', ['user_id' => $user->id, 'silence_all' => true]);
    }
}
