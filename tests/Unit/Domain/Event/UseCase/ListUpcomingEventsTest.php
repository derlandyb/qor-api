<?php

namespace Tests\Unit\Domain\Event\UseCase;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Event\EventPage;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Event\UseCase\ListUpcomingEvents;
use QOR\App\Domain\Shared\Enum\City;

class ListUpcomingEventsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_GIVEN_no_filters_WHEN_executing_THEN_repository_is_queried_without_city_or_genre(): void
    {
        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findUpcoming')
            ->once()
            ->with(null, null, null)
            ->andReturn(new EventPage(items: [], nextCursor: null));

        $useCase = new ListUpcomingEvents($repository);

        $useCase->execute();
    }

    public function test_GIVEN_a_city_filter_WHEN_executing_THEN_it_is_passed_to_the_repository(): void
    {
        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findUpcoming')
            ->once()
            ->with(City::Serra, null, null)
            ->andReturn(new EventPage(items: [], nextCursor: null));

        $useCase = new ListUpcomingEvents($repository);

        $useCase->execute(city: City::Serra);
    }

    public function test_GIVEN_a_genre_filter_WHEN_executing_THEN_it_is_passed_to_the_repository(): void
    {
        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findUpcoming')
            ->once()
            ->with(null, 3, null)
            ->andReturn(new EventPage(items: [], nextCursor: null));

        $useCase = new ListUpcomingEvents($repository);

        $useCase->execute(genreId: 3);
    }

    public function test_GIVEN_city_and_genre_filters_WHEN_executing_THEN_both_are_AND_combined(): void
    {
        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findUpcoming')
            ->once()
            ->with(City::Vitoria, 5, null)
            ->andReturn(new EventPage(items: [], nextCursor: null));

        $useCase = new ListUpcomingEvents($repository);

        $useCase->execute(city: City::Vitoria, genreId: 5);
    }

    public function test_GIVEN_a_cursor_WHEN_executing_THEN_it_is_passed_to_the_repository(): void
    {
        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findUpcoming')
            ->once()
            ->with(null, null, 'opaque-cursor')
            ->andReturn(new EventPage(items: [], nextCursor: null));

        $useCase = new ListUpcomingEvents($repository);

        $useCase->execute(cursor: 'opaque-cursor');
    }

    public function test_GIVEN_the_repository_returns_an_empty_page_WHEN_executing_THEN_the_empty_page_is_returned(): void
    {
        $repository = Mockery::mock(EventRepository::class);
        $emptyPage = new EventPage(items: [], nextCursor: null);
        $repository->shouldReceive('findUpcoming')->once()->andReturn($emptyPage);

        $useCase = new ListUpcomingEvents($repository);

        $this->assertSame($emptyPage, $useCase->execute());
    }
}
