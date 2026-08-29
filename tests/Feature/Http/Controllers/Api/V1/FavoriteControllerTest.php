<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use QOR\App\Infrastructure\Persistence\Eloquent\EventModel;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\TestCase;

class FavoriteControllerTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(UserModel $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('mobile')->plainTextToken];
    }

    public function test_GIVEN_no_token_WHEN_toggling_a_favorite_THEN_it_is_rejected(): void
    {
        $event = EventModel::factory()->published()->create();

        $this->postJson("/api/v1/events/{$event->id}/favorite")->assertStatus(401);
    }

    public function test_GIVEN_an_unfavorited_event_WHEN_toggling_THEN_it_becomes_favorited(): void
    {
        $user = UserModel::factory()->create();
        $event = EventModel::factory()->published()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson("/api/v1/events/{$event->id}/favorite");

        $response->assertStatus(200)->assertJsonPath('data.favorited', true);
        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'event_id' => $event->id]);
    }

    public function test_GIVEN_a_favorited_event_WHEN_toggling_again_THEN_it_becomes_unfavorited(): void
    {
        $user = UserModel::factory()->create();
        $event = EventModel::factory()->published()->create();

        $headers = $this->authHeader($user);
        $this->withHeaders($headers)->postJson("/api/v1/events/{$event->id}/favorite");
        $response = $this->withHeaders($headers)->postJson("/api/v1/events/{$event->id}/favorite");

        $response->assertStatus(200)->assertJsonPath('data.favorited', false);
        $this->assertDatabaseMissing('favorites', ['user_id' => $user->id, 'event_id' => $event->id]);
    }

    public function test_GIVEN_a_nonexistent_event_WHEN_toggling_THEN_it_returns_404(): void
    {
        $user = UserModel::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/events/999999/favorite');

        $response->assertStatus(404);
    }

    public function test_GIVEN_favorited_events_WHEN_listing_favorites_THEN_it_returns_them(): void
    {
        $user = UserModel::factory()->create();
        $event = EventModel::factory()->published()->create();

        $this->withHeaders($this->authHeader($user))->postJson("/api/v1/events/{$event->id}/favorite");

        $response = $this->withHeaders($this->authHeader($user))->getJson('/api/v1/profile/favorites');

        $response->assertStatus(200)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $event->id);
    }

    public function test_GIVEN_no_favorites_WHEN_listing_favorites_THEN_it_returns_an_empty_list(): void
    {
        $user = UserModel::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))->getJson('/api/v1/profile/favorites');

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }
}
