<?php

namespace Tests\Unit\Domain\Social\UseCase;

use DomainException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Social\Enum\FriendshipStatus;
use QOR\App\Domain\Social\Friendship;
use QOR\App\Domain\Social\FriendshipRepository;
use QOR\App\Domain\Social\UseCase\SendFriendRequest;

final class SendFriendRequestTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_GIVEN_no_existing_row_between_two_users_WHEN_sending_a_request_THEN_a_pending_friendship_is_created(): void
    {
        $created = new Friendship(id: 1, requesterId: 1, recipientId: 2, status: FriendshipStatus::Pending);

        $repository = Mockery::mock(FriendshipRepository::class);
        $repository->shouldReceive('findBetween')->once()->with(1, 2)->andReturn(null);
        $repository->shouldReceive('create')->once()->with(1, 2)->andReturn($created);

        $useCase = new SendFriendRequest($repository);

        $result = $useCase->execute(1, 2);

        $this->assertSame($created, $result);
    }

    public function test_GIVEN_the_same_requester_already_has_a_pending_outgoing_request_WHEN_sending_again_THEN_the_existing_request_is_returned(): void
    {
        $existing = new Friendship(id: 1, requesterId: 1, recipientId: 2, status: FriendshipStatus::Pending);

        $repository = Mockery::mock(FriendshipRepository::class);
        $repository->shouldReceive('findBetween')->once()->with(1, 2)->andReturn($existing);
        $repository->shouldNotReceive('create');
        $repository->shouldNotReceive('accept');

        $useCase = new SendFriendRequest($repository);

        $result = $useCase->execute(1, 2);

        $this->assertSame($existing, $result);
    }

    public function test_GIVEN_a_reverse_pending_request_already_exists_WHEN_sending_THEN_it_auto_accepts(): void
    {
        $reverse = new Friendship(id: 1, requesterId: 2, recipientId: 1, status: FriendshipStatus::Pending);
        $accepted = $reverse->accept();

        $repository = Mockery::mock(FriendshipRepository::class);
        $repository->shouldReceive('findBetween')->once()->with(1, 2)->andReturn($reverse);
        $repository->shouldReceive('accept')->once()->with(1)->andReturn($accepted);
        $repository->shouldNotReceive('create');

        $useCase = new SendFriendRequest($repository);

        $result = $useCase->execute(1, 2);

        $this->assertSame(FriendshipStatus::Accepted, $result->status);
    }

    public function test_GIVEN_the_two_users_are_already_friends_WHEN_sending_a_request_THEN_it_throws(): void
    {
        $existing = new Friendship(id: 1, requesterId: 2, recipientId: 1, status: FriendshipStatus::Accepted);

        $repository = Mockery::mock(FriendshipRepository::class);
        $repository->shouldReceive('findBetween')->once()->with(1, 2)->andReturn($existing);
        $repository->shouldNotReceive('create');
        $repository->shouldNotReceive('accept');

        $useCase = new SendFriendRequest($repository);

        $this->expectException(DomainException::class);

        $useCase->execute(1, 2);
    }

    public function test_GIVEN_the_requester_and_recipient_are_the_same_user_WHEN_sending_a_request_THEN_it_throws(): void
    {
        $repository = Mockery::mock(FriendshipRepository::class);
        $repository->shouldNotReceive('findBetween');
        $repository->shouldNotReceive('create');

        $useCase = new SendFriendRequest($repository);

        $this->expectException(DomainException::class);

        $useCase->execute(1, 1);
    }
}
