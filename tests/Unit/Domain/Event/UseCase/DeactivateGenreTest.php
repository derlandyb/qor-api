<?php

namespace Tests\Unit\Domain\Event\UseCase;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Event\Genre;
use QOR\App\Domain\Event\GenreRepository;
use QOR\App\Domain\Event\UseCase\DeactivateGenre;

class DeactivateGenreTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_GIVEN_an_active_genre_WHEN_deactivating_THEN_it_becomes_inactive(): void
    {
        $genre = new Genre(id: 1, name: 'Rock', slug: 'rock', isActive: true);

        $repository = Mockery::mock(GenreRepository::class);
        $repository->shouldReceive('findById')->once()->with(1)->andReturn($genre);
        $repository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Genre $g) => $g->id === 1 && $g->isActive === false))
            ->andReturnUsing(fn (Genre $g) => $g);

        $useCase = new DeactivateGenre($repository);

        $result = $useCase->execute(1);

        $this->assertFalse($result->isActive);
    }
}
