<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use QOR\App\Domain\Notification\NotificationSender;
use QOR\App\Infrastructure\Notification\FcmPushSender;
use QOR\App\Infrastructure\Notification\SesEmailSender;
use QOR\App\Infrastructure\Persistence\Eloquent\EventModel;
use QOR\App\Infrastructure\Persistence\Eloquent\FavoriteModel;
use QOR\App\Infrastructure\Persistence\Eloquent\FriendshipModel;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\TestCase;

class ShareControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The real FCM/SES senders validate that the dispatcher's payload
        // carries channel-specific keys (device_token/title/body,
        // email/subject/body) that ShareEvent (T72, out of this task's
        // scope) doesn't populate yet — assembling that enriched payload is
        // NotificationDispatcher/sender wiring, not ShareController's
        // concern. Swapped for a no-op double here so this suite tests only
        // the controller's request/response contract, consistent with the
        // dedicated NotificationDispatcher/sender unit and feature tests
        // that already cover delivery itself.
        $noopSender = new class implements NotificationSender
        {
            public function send(int $userId, array $payload): void
            {
            }
        };
        $this->app->instance(FcmPushSender::class, $noopSender);
        $this->app->instance(SesEmailSender::class, $noopSender);
    }

    private function authHeader(UserModel $user): array
    {
        Auth::forgetGuards();

        return ['Authorization' => 'Bearer '.$user->createToken('mobile')->plainTextToken];
    }

    public function test_GIVEN_no_token_WHEN_listing_friends_interested_THEN_it_is_rejected(): void
    {
        $event = EventModel::factory()->published()->create();

        $this->getJson("/api/v1/events/{$event->id}/friends-interested")->assertStatus(401);
    }

    public function test_GIVEN_a_friend_who_favorited_the_event_WHEN_listing_friends_interested_THEN_it_appears(): void
    {
        $user = UserModel::factory()->create();
        $friend = UserModel::factory()->create();
        $event = EventModel::factory()->published()->create();

        FriendshipModel::factory()->accepted()->create([
            'requester_id' => $user->id,
            'recipient_id' => $friend->id,
        ]);
        FavoriteModel::create(['user_id' => $friend->id, 'event_id' => $event->id, 'created_at' => now()]);

        $response = $this->withHeaders($this->authHeader($user))
            ->getJson("/api/v1/events/{$event->id}/friends-interested");

        $response->assertStatus(200)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $friend->id);
    }

    public function test_GIVEN_a_nonexistent_event_WHEN_listing_friends_interested_THEN_it_returns_404(): void
    {
        $user = UserModel::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/events/999999/friends-interested');

        $response->assertStatus(404);
    }

    public function test_GIVEN_an_accepted_friend_WHEN_sharing_an_event_THEN_it_succeeds(): void
    {
        $user = UserModel::factory()->create();
        $friend = UserModel::factory()->create();
        $event = EventModel::factory()->published()->create();

        FriendshipModel::factory()->accepted()->create([
            'requester_id' => $user->id,
            'recipient_id' => $friend->id,
        ]);

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson("/api/v1/events/{$event->id}/share", ['friend_user_id' => $friend->id]);

        $response->assertStatus(200);
    }

    public function test_GIVEN_a_non_friend_WHEN_sharing_an_event_THEN_it_returns_422(): void
    {
        $user = UserModel::factory()->create();
        $stranger = UserModel::factory()->create();
        $event = EventModel::factory()->published()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson("/api/v1/events/{$event->id}/share", ['friend_user_id' => $stranger->id]);

        $response->assertStatus(422);
    }

    public function test_GIVEN_a_nonexistent_event_WHEN_sharing_THEN_it_returns_404(): void
    {
        $user = UserModel::factory()->create();
        $friend = UserModel::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/events/999999/share', ['friend_user_id' => $friend->id]);

        $response->assertStatus(404);
    }
}
