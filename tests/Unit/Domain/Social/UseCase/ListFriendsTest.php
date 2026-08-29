<?php

namespace Tests\Unit\Domain\Social\UseCase;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Social\Enum\FriendshipStatus;
use QOR\App\Domain\Social\Friendship;
use QOR\App\Domain\Social\FriendPage;
use QOR\App\Domain\Social\FriendshipRepository;
use QOR\App\Domain\Social\UseCase\ListFriends;

final class ListFriendsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_GIVEN_a_fan_with_friends_WHEN_listing_THEN_it_delegates_to_the_repository(): void
    {
        $page = new FriendPage(
            items: [new Friendship(id: 1, requesterId: 1, recipientId: 2, status: FriendshipStatus::Accepted)],
            nextCursor: null,
        );

        $repository = Mockery::mock(FriendshipRepository::class);
        $repository->shouldReceive('listAccepted')->once()->with(1, null)->andReturn($page);

        $useCase = new ListFriends($repository);

        $this->assertSame($page, $useCase->execute(1, null));
    }

    public function test_GIVEN_a_fan_with_no_friends_WHEN_listing_THEN_it_returns_an_empty_page(): void
    {
        $page = new FriendPage(items: [], nextCursor: null);

        $repository = Mockery::mock(FriendshipRepository::class);
        $repository->shouldReceive('listAccepted')->once()->with(1, null)->andReturn($page);

        $useCase = new ListFriends($repository);

        $result = $useCase->execute(1, null);

        $this->assertSame([], $result->items);
    }

    public function test_GIVEN_a_cursor_WHEN_listing_THEN_it_is_forwarded_to_the_repository(): void
    {
        $page = new FriendPage(items: [], nextCursor: 'abc');

        $repository = Mockery::mock(FriendshipRepository::class);
        $repository->shouldReceive('listAccepted')->once()->with(1, 'cursor')->andReturn($page);

        $useCase = new ListFriends($repository);

        $this->assertSame($page, $useCase->execute(1, 'cursor'));
    }
}
