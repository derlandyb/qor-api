<?php

namespace Tests\Unit\Domain\Social;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Social\Enum\FriendshipStatus;
use QOR\App\Domain\Social\Friendship;

final class FriendshipTest extends TestCase
{
    public function test_GIVEN_two_distinct_users_WHEN_creating_a_friendship_THEN_it_defaults_to_pending(): void
    {
        $friendship = new Friendship(id: null, requesterId: 1, recipientId: 2);

        $this->assertSame(FriendshipStatus::Pending, $friendship->status);
    }

    public function test_GIVEN_the_same_user_as_requester_and_recipient_WHEN_creating_a_friendship_THEN_it_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Friendship(id: null, requesterId: 1, recipientId: 1);
    }

    public function test_GIVEN_a_non_positive_user_id_WHEN_creating_a_friendship_THEN_it_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Friendship(id: null, requesterId: 0, recipientId: 2);
    }

    public function test_GIVEN_a_pending_friendship_WHEN_accepting_THEN_the_status_becomes_accepted(): void
    {
        $friendship = new Friendship(id: 1, requesterId: 1, recipientId: 2);

        $accepted = $friendship->accept();

        $this->assertSame(FriendshipStatus::Accepted, $accepted->status);
        $this->assertSame($friendship->id, $accepted->id);
    }
}
