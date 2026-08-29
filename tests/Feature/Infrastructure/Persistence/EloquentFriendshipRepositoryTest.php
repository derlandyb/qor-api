<?php

namespace Tests\Feature\Infrastructure\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use QOR\App\Domain\Social\Enum\FriendshipStatus;
use QOR\App\Infrastructure\Persistence\Eloquent\FriendshipModel;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;
use QOR\App\Infrastructure\Persistence\EloquentFriendshipRepository;
use Tests\TestCase;

class EloquentFriendshipRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_two_users_WHEN_creating_a_request_THEN_it_is_persisted_as_pending(): void
    {
        $requester = UserModel::factory()->create();
        $recipient = UserModel::factory()->create();

        $repository = new EloquentFriendshipRepository();

        $friendship = $repository->create($requester->id, $recipient->id);

        $this->assertSame(FriendshipStatus::Pending, $friendship->status);
        $this->assertDatabaseHas('friendships', [
            'requester_id' => $requester->id,
            'recipient_id' => $recipient->id,
            'status' => FriendshipStatus::Pending->value,
        ]);
    }

    public function test_GIVEN_a_pending_request_WHEN_accepting_THEN_the_status_becomes_accepted(): void
    {
        $model = FriendshipModel::factory()->create();
        $repository = new EloquentFriendshipRepository();

        $accepted = $repository->accept($model->id);

        $this->assertSame(FriendshipStatus::Accepted, $accepted->status);
        $this->assertDatabaseHas('friendships', ['id' => $model->id, 'status' => FriendshipStatus::Accepted->value]);
    }

    public function test_GIVEN_a_pending_request_WHEN_rejecting_THEN_the_row_is_removed(): void
    {
        $model = FriendshipModel::factory()->create();
        $repository = new EloquentFriendshipRepository();

        $repository->reject($model->id);

        $this->assertDatabaseMissing('friendships', ['id' => $model->id]);
    }

    public function test_GIVEN_a_mutual_friendship_WHEN_removing_it_from_either_side_THEN_the_row_is_deleted(): void
    {
        $a = UserModel::factory()->create();
        $b = UserModel::factory()->create();
        FriendshipModel::factory()->accepted()->create(['requester_id' => $a->id, 'recipient_id' => $b->id]);

        $repository = new EloquentFriendshipRepository();

        $repository->remove($b->id, $a->id);

        $this->assertDatabaseMissing('friendships', ['requester_id' => $a->id, 'recipient_id' => $b->id]);
    }

    public function test_GIVEN_accepted_and_pending_friendships_WHEN_listing_accepted_THEN_only_accepted_ones_are_returned(): void
    {
        $user = UserModel::factory()->create();
        $other1 = UserModel::factory()->create();
        $other2 = UserModel::factory()->create();

        FriendshipModel::factory()->accepted()->create(['requester_id' => $user->id, 'recipient_id' => $other1->id]);
        FriendshipModel::factory()->create(['requester_id' => $user->id, 'recipient_id' => $other2->id]);

        $repository = new EloquentFriendshipRepository();

        $page = $repository->listAccepted($user->id, null);

        $this->assertCount(1, $page->items);
        $this->assertSame(FriendshipStatus::Accepted, $page->items[0]->status);
    }

    public function test_GIVEN_no_accepted_friendships_WHEN_listing_accepted_THEN_an_empty_page_is_returned(): void
    {
        $user = UserModel::factory()->create();
        $repository = new EloquentFriendshipRepository();

        $page = $repository->listAccepted($user->id, null);

        $this->assertCount(0, $page->items);
        $this->assertNull($page->nextCursor);
    }

    public function test_GIVEN_incoming_and_outgoing_pending_requests_WHEN_listing_incoming_pending_THEN_only_incoming_requests_are_returned(): void
    {
        $user = UserModel::factory()->create();
        $incomingRequester = UserModel::factory()->create();
        $outgoingRecipient = UserModel::factory()->create();

        FriendshipModel::factory()->create(['requester_id' => $incomingRequester->id, 'recipient_id' => $user->id]);
        FriendshipModel::factory()->create(['requester_id' => $user->id, 'recipient_id' => $outgoingRecipient->id]);

        $repository = new EloquentFriendshipRepository();

        $incoming = $repository->listIncomingPending($user->id);

        $this->assertCount(1, $incoming);
        $this->assertSame($incomingRequester->id, $incoming[0]->requesterId);
    }

    public function test_GIVEN_a_friendship_between_two_users_WHEN_finding_between_them_in_either_order_THEN_the_same_row_is_returned(): void
    {
        $a = UserModel::factory()->create();
        $b = UserModel::factory()->create();
        FriendshipModel::factory()->create(['requester_id' => $a->id, 'recipient_id' => $b->id]);

        $repository = new EloquentFriendshipRepository();

        $this->assertNotNull($repository->findBetween($a->id, $b->id));
        $this->assertNotNull($repository->findBetween($b->id, $a->id));
    }

    public function test_GIVEN_no_friendship_between_two_users_WHEN_finding_between_them_THEN_it_returns_null(): void
    {
        $a = UserModel::factory()->create();
        $b = UserModel::factory()->create();

        $repository = new EloquentFriendshipRepository();

        $this->assertNull($repository->findBetween($a->id, $b->id));
    }

    public function test_GIVEN_an_existing_friendship_row_WHEN_finding_it_by_id_THEN_it_is_returned(): void
    {
        $model = FriendshipModel::factory()->create();
        $repository = new EloquentFriendshipRepository();

        $friendship = $repository->findById($model->id);

        $this->assertNotNull($friendship);
        $this->assertSame($model->requester_id, $friendship->requesterId);
        $this->assertSame($model->recipient_id, $friendship->recipientId);
    }

    public function test_GIVEN_no_friendship_row_WHEN_finding_it_by_id_THEN_it_returns_null(): void
    {
        $repository = new EloquentFriendshipRepository();

        $this->assertNull($repository->findById(999999));
    }
}
