<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use QOR\App\Infrastructure\Persistence\Eloquent\FriendshipModel;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\TestCase;

class FriendshipControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Laravel\Sanctum\Guard is wrapped in a RequestGuard, which caches the
     * resolved user for the guard instance's lifetime. Since these tests
     * issue several requests as *different* fans within a single test
     * method (unlike every other Feature test in this suite, which only
     * ever authenticates as one user per test), that cache must be cleared
     * before switching identities, or the second header is ignored and the
     * first request's user is reused.
     */
    private function authHeader(UserModel $user): array
    {
        Auth::forgetGuards();

        return ['Authorization' => 'Bearer '.$user->createToken('mobile')->plainTextToken];
    }

    public function test_GIVEN_no_token_WHEN_sending_a_friend_request_THEN_it_is_rejected(): void
    {
        $recipient = UserModel::factory()->create();

        $this->postJson('/api/v1/friends/requests', ['recipient_user_id' => $recipient->id])
            ->assertStatus(401);
    }

    public function test_GIVEN_two_distinct_fans_WHEN_sending_a_friend_request_THEN_it_is_created_as_pending(): void
    {
        $requester = UserModel::factory()->create();
        $recipient = UserModel::factory()->create();

        $response = $this->withHeaders($this->authHeader($requester))
            ->postJson('/api/v1/friends/requests', ['recipient_user_id' => $recipient->id]);

        $response->assertStatus(201)->assertJsonPath('data.status', 'pending');
        $this->assertDatabaseHas('friendships', [
            'requester_id' => $requester->id,
            'recipient_id' => $recipient->id,
            'status' => 'pending',
        ]);
    }

    public function test_GIVEN_a_duplicate_pending_request_WHEN_sending_it_again_THEN_it_is_not_duplicated(): void
    {
        $requester = UserModel::factory()->create();
        $recipient = UserModel::factory()->create();

        $headers = $this->authHeader($requester);
        $this->withHeaders($headers)->postJson('/api/v1/friends/requests', ['recipient_user_id' => $recipient->id]);
        $this->withHeaders($headers)->postJson('/api/v1/friends/requests', ['recipient_user_id' => $recipient->id]);

        $this->assertSame(1, FriendshipModel::where('requester_id', $requester->id)
            ->where('recipient_id', $recipient->id)->count());
    }

    public function test_GIVEN_a_pending_request_from_the_other_direction_WHEN_sending_a_request_THEN_it_auto_accepts(): void
    {
        $userA = UserModel::factory()->create();
        $userB = UserModel::factory()->create();

        $this->withHeaders($this->authHeader($userA))
            ->postJson('/api/v1/friends/requests', ['recipient_user_id' => $userB->id]);

        $response = $this->withHeaders($this->authHeader($userB))
            ->postJson('/api/v1/friends/requests', ['recipient_user_id' => $userA->id]);

        $response->assertStatus(201)->assertJsonPath('data.status', 'accepted');
        $this->assertDatabaseHas('friendships', [
            'requester_id' => $userA->id,
            'recipient_id' => $userB->id,
            'status' => 'accepted',
        ]);
    }

    public function test_GIVEN_already_accepted_friends_WHEN_sending_a_request_again_THEN_it_returns_422(): void
    {
        $userA = UserModel::factory()->create();
        $userB = UserModel::factory()->create();
        FriendshipModel::factory()->accepted()->create([
            'requester_id' => $userA->id,
            'recipient_id' => $userB->id,
        ]);

        $response = $this->withHeaders($this->authHeader($userA))
            ->postJson('/api/v1/friends/requests', ['recipient_user_id' => $userB->id]);

        $response->assertStatus(422);
    }

    public function test_GIVEN_an_incoming_pending_request_WHEN_listing_requests_THEN_it_appears(): void
    {
        $requester = UserModel::factory()->create();
        $recipient = UserModel::factory()->create();
        FriendshipModel::factory()->create([
            'requester_id' => $requester->id,
            'recipient_id' => $recipient->id,
        ]);

        $response = $this->withHeaders($this->authHeader($recipient))->getJson('/api/v1/friends/requests');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_GIVEN_a_pending_request_WHEN_the_recipient_accepts_THEN_it_becomes_accepted(): void
    {
        $requester = UserModel::factory()->create();
        $recipient = UserModel::factory()->create();
        $friendship = FriendshipModel::factory()->create([
            'requester_id' => $requester->id,
            'recipient_id' => $recipient->id,
        ]);

        $response = $this->withHeaders($this->authHeader($recipient))
            ->postJson("/api/v1/friends/requests/{$friendship->id}/accept");

        $response->assertStatus(200)->assertJsonPath('data.status', 'accepted');
    }

    public function test_GIVEN_a_pending_request_WHEN_someone_else_tries_to_accept_THEN_it_is_rejected(): void
    {
        $requester = UserModel::factory()->create();
        $recipient = UserModel::factory()->create();
        $stranger = UserModel::factory()->create();
        $friendship = FriendshipModel::factory()->create([
            'requester_id' => $requester->id,
            'recipient_id' => $recipient->id,
        ]);

        $response = $this->withHeaders($this->authHeader($stranger))
            ->postJson("/api/v1/friends/requests/{$friendship->id}/accept");

        $response->assertStatus(422);
    }

    public function test_GIVEN_a_pending_request_WHEN_the_recipient_rejects_THEN_it_is_removed(): void
    {
        $requester = UserModel::factory()->create();
        $recipient = UserModel::factory()->create();
        $friendship = FriendshipModel::factory()->create([
            'requester_id' => $requester->id,
            'recipient_id' => $recipient->id,
        ]);

        $response = $this->withHeaders($this->authHeader($recipient))
            ->postJson("/api/v1/friends/requests/{$friendship->id}/reject");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('friendships', ['id' => $friendship->id]);
    }

    public function test_GIVEN_accepted_friends_WHEN_removing_the_friendship_THEN_it_is_deleted(): void
    {
        $userA = UserModel::factory()->create();
        $userB = UserModel::factory()->create();
        FriendshipModel::factory()->accepted()->create([
            'requester_id' => $userA->id,
            'recipient_id' => $userB->id,
        ]);

        $response = $this->withHeaders($this->authHeader($userA))->deleteJson("/api/v1/friends/{$userB->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('friendships', ['requester_id' => $userA->id, 'recipient_id' => $userB->id]);
    }

    public function test_GIVEN_accepted_friends_WHEN_listing_friends_THEN_they_appear(): void
    {
        $userA = UserModel::factory()->create();
        $userB = UserModel::factory()->create();
        FriendshipModel::factory()->accepted()->create([
            'requester_id' => $userA->id,
            'recipient_id' => $userB->id,
        ]);

        $response = $this->withHeaders($this->authHeader($userA))->getJson('/api/v1/friends');

        $response->assertStatus(200)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.friend.id', $userB->id);
    }
}
