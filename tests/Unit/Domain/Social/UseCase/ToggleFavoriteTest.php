<?php

namespace Tests\Unit\Domain\Social\UseCase;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Social\Enum\FavoriteState;
use QOR\App\Domain\Social\FavoritePage;
use QOR\App\Domain\Social\FavoriteRepository;
use QOR\App\Domain\Social\UseCase\ToggleFavorite;

final class ToggleFavoriteTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_GIVEN_an_unfavorited_event_WHEN_toggling_THEN_it_becomes_favorited(): void
    {
        $repository = Mockery::mock(FavoriteRepository::class);
        $repository->shouldReceive('toggle')->once()->with(1, 10)->andReturn(FavoriteState::Favorited);

        $useCase = new ToggleFavorite($repository);

        $this->assertSame(FavoriteState::Favorited, $useCase->execute(1, 10));
    }

    public function test_GIVEN_a_favorited_event_WHEN_toggling_again_THEN_it_becomes_unfavorited(): void
    {
        $repository = Mockery::mock(FavoriteRepository::class);
        $repository->shouldReceive('toggle')->once()->with(1, 10)->andReturn(FavoriteState::Unfavorited);

        $useCase = new ToggleFavorite($repository);

        $this->assertSame(FavoriteState::Unfavorited, $useCase->execute(1, 10));
    }

    public function test_GIVEN_toggle_is_called_twice_in_a_row_WHEN_toggling_THEN_it_is_idempotent_per_call(): void
    {
        $repository = Mockery::mock(FavoriteRepository::class);
        $repository->shouldReceive('toggle')->once()->with(1, 10)->andReturn(FavoriteState::Favorited);
        $repository->shouldReceive('toggle')->once()->with(1, 10)->andReturn(FavoriteState::Unfavorited);

        $useCase = new ToggleFavorite($repository);

        $this->assertSame(FavoriteState::Favorited, $useCase->execute(1, 10));
        $this->assertSame(FavoriteState::Unfavorited, $useCase->execute(1, 10));
    }

    public function test_GIVEN_a_fan_profile_WHEN_listing_favorites_THEN_it_delegates_to_the_repository_page(): void
    {
        $page = new FavoritePage(items: [], nextCursor: null);

        $repository = Mockery::mock(FavoriteRepository::class);
        $repository->shouldReceive('listForUser')->once()->with(1, null)->andReturn($page);

        $useCase = new ToggleFavorite($repository);

        $this->assertSame($page, $useCase->listForUser(1, null));
    }

    public function test_GIVEN_a_cursor_WHEN_listing_favorites_THEN_the_cursor_is_forwarded_to_the_repository(): void
    {
        $page = new FavoritePage(items: [], nextCursor: 'next');

        $repository = Mockery::mock(FavoriteRepository::class);
        $repository->shouldReceive('listForUser')->once()->with(1, 'abc')->andReturn($page);

        $useCase = new ToggleFavorite($repository);

        $this->assertSame($page, $useCase->listForUser(1, 'abc'));
    }
}
