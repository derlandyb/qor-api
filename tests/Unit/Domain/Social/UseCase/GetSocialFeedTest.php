<?php

namespace Tests\Unit\Domain\Social\UseCase;

use DateTimeImmutable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\Social\Enum\FriendshipStatus;
use QOR\App\Domain\Social\FavoritePage;
use QOR\App\Domain\Social\FavoriteRepository;
use QOR\App\Domain\Social\Friendship;
use QOR\App\Domain\Social\FriendPage;
use QOR\App\Domain\Social\FriendshipRepository;
use QOR\App\Domain\Social\UseCase\GetSocialFeed;

final class GetSocialFeedTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function event(int $id): Event
    {
        return new Event(
            id: $id,
            createdByType: EventCreatedByType::VenueAdmin,
            createdById: 1,
            title: "Show {$id}",
            description: 'Descrição',
            startsAt: new DateTimeImmutable('+1 week'),
            city: City::Vitoria,
            genreId: 1,
            isFree: true,
        );
    }

    public function test_GIVEN_a_friend_favorited_a_published_event_WHEN_getting_the_feed_THEN_it_appears_with_the_friend_id(): void
    {
        $friendship = new Friendship(id: 1, requesterId: 1, recipientId: 2, status: FriendshipStatus::Accepted);

        $friendships = Mockery::mock(FriendshipRepository::class);
        $friendships->shouldReceive('listAccepted')->once()->with(1, null)
            ->andReturn(new FriendPage(items: [$friendship], nextCursor: null));

        $favorites = Mockery::mock(FavoriteRepository::class);
        $favorites->shouldReceive('listForUser')->once()->with(2, null)
            ->andReturn(new FavoritePage(items: [$this->event(10)], nextCursor: null));

        $useCase = new GetSocialFeed($friendships, $favorites);

        $page = $useCase->execute(1, null);

        $this->assertCount(1, $page->items);
        $this->assertSame(10, $page->items[0]['event']->id);
        $this->assertSame(2, $page->items[0]['friendUserId']);
    }

    public function test_GIVEN_a_fan_with_no_friends_WHEN_getting_the_feed_THEN_it_returns_an_empty_page(): void
    {
        $friendships = Mockery::mock(FriendshipRepository::class);
        $friendships->shouldReceive('listAccepted')->once()->with(1, null)
            ->andReturn(new FriendPage(items: [], nextCursor: null));

        $favorites = Mockery::mock(FavoriteRepository::class);
        $favorites->shouldNotReceive('listForUser');

        $useCase = new GetSocialFeed($friendships, $favorites);

        $page = $useCase->execute(1, null);

        $this->assertSame([], $page->items);
    }

    public function test_GIVEN_friends_with_no_favorites_WHEN_getting_the_feed_THEN_it_returns_an_empty_page(): void
    {
        $friendship = new Friendship(id: 1, requesterId: 1, recipientId: 2, status: FriendshipStatus::Accepted);

        $friendships = Mockery::mock(FriendshipRepository::class);
        $friendships->shouldReceive('listAccepted')->once()->with(1, null)
            ->andReturn(new FriendPage(items: [$friendship], nextCursor: null));

        $favorites = Mockery::mock(FavoriteRepository::class);
        $favorites->shouldReceive('listForUser')->once()->with(2, null)
            ->andReturn(new FavoritePage(items: [], nextCursor: null));

        $useCase = new GetSocialFeed($friendships, $favorites);

        $this->assertSame([], $useCase->execute(1, null)->items);
    }

    public function test_GIVEN_a_cursor_WHEN_getting_the_feed_THEN_it_is_forwarded_to_the_friend_list_and_the_feed_cursor_reflects_it(): void
    {
        $friendships = Mockery::mock(FriendshipRepository::class);
        $friendships->shouldReceive('listAccepted')->once()->with(1, 'abc')
            ->andReturn(new FriendPage(items: [], nextCursor: 'next'));

        $favorites = Mockery::mock(FavoriteRepository::class);

        $useCase = new GetSocialFeed($friendships, $favorites);

        $page = $useCase->execute(1, 'abc');

        $this->assertSame('next', $page->nextCursor);
    }
}
