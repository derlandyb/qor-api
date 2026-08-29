<?php

namespace Tests\Unit\Domain\Social\UseCase;

use DateTimeImmutable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Social\Enum\FriendshipStatus;
use QOR\App\Domain\Social\FavoriteRepository;
use QOR\App\Domain\Social\Friendship;
use QOR\App\Domain\Social\FriendPage;
use QOR\App\Domain\Social\FriendshipRepository;
use QOR\App\Domain\Social\UseCase\GetFriendsInterested;
use QOR\App\Domain\User\User;
use QOR\App\Domain\User\UserRepository;

final class GetFriendsInterestedTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function user(int $id): User
    {
        return new User(
            id: $id,
            name: "Fan {$id}",
            email: "fan{$id}@example.com",
            birthdate: new DateTimeImmutable('2000-01-01'),
            passwordHash: 'hash',
        );
    }

    public function test_GIVEN_a_mutual_friend_favorited_the_event_WHEN_getting_interested_friends_THEN_that_friend_is_returned(): void
    {
        $friendship = new Friendship(id: 1, requesterId: 1, recipientId: 2, status: FriendshipStatus::Accepted);

        $friendships = Mockery::mock(FriendshipRepository::class);
        $friendships->shouldReceive('listAccepted')->once()->with(1, null)
            ->andReturn(new FriendPage(items: [$friendship], nextCursor: null));

        $favorites = Mockery::mock(FavoriteRepository::class);
        $favorites->shouldReceive('hasUserFavorited')->once()->with(2, 10)->andReturn(true);

        $users = Mockery::mock(UserRepository::class);
        $users->shouldReceive('findById')->once()->with(2)->andReturn($this->user(2));

        $useCase = new GetFriendsInterested($friendships, $favorites, $users);

        $result = $useCase->execute(1, 10);

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]->id);
    }

    public function test_GIVEN_a_mutual_friend_did_not_favorite_the_event_WHEN_getting_interested_friends_THEN_that_friend_is_excluded(): void
    {
        $friendship = new Friendship(id: 1, requesterId: 1, recipientId: 2, status: FriendshipStatus::Accepted);

        $friendships = Mockery::mock(FriendshipRepository::class);
        $friendships->shouldReceive('listAccepted')->once()->with(1, null)
            ->andReturn(new FriendPage(items: [$friendship], nextCursor: null));

        $favorites = Mockery::mock(FavoriteRepository::class);
        $favorites->shouldReceive('hasUserFavorited')->once()->with(2, 10)->andReturn(false);

        $users = Mockery::mock(UserRepository::class);
        $users->shouldNotReceive('findById');

        $useCase = new GetFriendsInterested($friendships, $favorites, $users);

        $this->assertSame([], $useCase->execute(1, 10));
    }

    public function test_GIVEN_the_fan_has_zero_friends_WHEN_getting_interested_friends_THEN_it_returns_an_empty_array_not_an_error(): void
    {
        $friendships = Mockery::mock(FriendshipRepository::class);
        $friendships->shouldReceive('listAccepted')->once()->with(1, null)
            ->andReturn(new FriendPage(items: [], nextCursor: null));

        $favorites = Mockery::mock(FavoriteRepository::class);
        $favorites->shouldNotReceive('hasUserFavorited');

        $users = Mockery::mock(UserRepository::class);

        $useCase = new GetFriendsInterested($friendships, $favorites, $users);

        $this->assertSame([], $useCase->execute(1, 10));
    }

    public function test_GIVEN_no_friends_are_interested_WHEN_getting_interested_friends_THEN_it_returns_an_empty_array_not_an_error(): void
    {
        $friendship = new Friendship(id: 1, requesterId: 1, recipientId: 3, status: FriendshipStatus::Accepted);

        $friendships = Mockery::mock(FriendshipRepository::class);
        $friendships->shouldReceive('listAccepted')->once()->with(1, null)
            ->andReturn(new FriendPage(items: [$friendship], nextCursor: null));

        $favorites = Mockery::mock(FavoriteRepository::class);
        $favorites->shouldReceive('hasUserFavorited')->once()->with(3, 10)->andReturn(false);

        $users = Mockery::mock(UserRepository::class);

        $useCase = new GetFriendsInterested($friendships, $favorites, $users);

        $this->assertSame([], $useCase->execute(1, 10));
    }
}
