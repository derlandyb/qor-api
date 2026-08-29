<?php

namespace Tests\Unit\Domain\Social\UseCase;

use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Social\Enum\FriendshipStatus;
use QOR\App\Domain\Social\Friendship;
use QOR\App\Domain\Social\FriendshipRepository;
use QOR\App\Domain\Social\UseCase\RespondToFriendRequest;

final class RespondToFriendRequestTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_GIVEN_the_recipient_responds_WHEN_accepting_THEN_the_request_is_accepted(): void
    {
        $pending = new Friendship(id: 5, requesterId: 1, recipientId: 2, status: FriendshipStatus::Pending);
        $accepted = $pending->accept();

        $repository = Mockery::mock(FriendshipRepository::class);
        $repository->shouldReceive('findById')->once()->with(5)->andReturn($pending);
        $repository->shouldReceive('accept')->once()->with(5)->andReturn($accepted);

        $useCase = new RespondToFriendRequest($repository);

        $result = $useCase->accept(5, 2);

        $this->assertSame(FriendshipStatus::Accepted, $result->status);
    }

    public function test_GIVEN_the_requester_tries_to_accept_their_own_request_WHEN_accepting_THEN_it_throws(): void
    {
        $pending = new Friendship(id: 5, requesterId: 1, recipientId: 2, status: FriendshipStatus::Pending);

        $repository = Mockery::mock(FriendshipRepository::class);
        $repository->shouldReceive('findById')->once()->with(5)->andReturn($pending);
        $repository->shouldNotReceive('accept');

        $useCase = new RespondToFriendRequest($repository);

        $this->expectException(InvalidArgumentException::class);

        $useCase->accept(5, 1);
    }

    public function test_GIVEN_the_recipient_responds_WHEN_rejecting_THEN_the_request_is_rejected(): void
    {
        $pending = new Friendship(id: 5, requesterId: 1, recipientId: 2, status: FriendshipStatus::Pending);

        $repository = Mockery::mock(FriendshipRepository::class);
        $repository->shouldReceive('findById')->once()->with(5)->andReturn($pending);
        $repository->shouldReceive('reject')->once()->with(5);

        $useCase = new RespondToFriendRequest($repository);

        $useCase->reject(5, 2);
    }

    public function test_GIVEN_the_requester_tries_to_reject_their_own_request_WHEN_rejecting_THEN_it_throws(): void
    {
        $pending = new Friendship(id: 5, requesterId: 1, recipientId: 2, status: FriendshipStatus::Pending);

        $repository = Mockery::mock(FriendshipRepository::class);
        $repository->shouldReceive('findById')->once()->with(5)->andReturn($pending);
        $repository->shouldNotReceive('reject');

        $useCase = new RespondToFriendRequest($repository);

        $this->expectException(InvalidArgumentException::class);

        $useCase->reject(5, 1);
    }

    public function test_GIVEN_a_request_that_does_not_exist_WHEN_accepting_THEN_it_throws(): void
    {
        $repository = Mockery::mock(FriendshipRepository::class);
        $repository->shouldReceive('findById')->once()->with(404)->andReturn(null);
        $repository->shouldNotReceive('accept');

        $useCase = new RespondToFriendRequest($repository);

        $this->expectException(InvalidArgumentException::class);

        $useCase->accept(404, 2);
    }

    public function test_GIVEN_a_fan_WHEN_listing_incoming_requests_THEN_it_delegates_to_the_repository(): void
    {
        $incoming = [new Friendship(id: 5, requesterId: 1, recipientId: 2, status: FriendshipStatus::Pending)];

        $repository = Mockery::mock(FriendshipRepository::class);
        $repository->shouldReceive('listIncomingPending')->once()->with(2)->andReturn($incoming);

        $useCase = new RespondToFriendRequest($repository);

        $this->assertSame($incoming, $useCase->listIncoming(2));
    }
}
