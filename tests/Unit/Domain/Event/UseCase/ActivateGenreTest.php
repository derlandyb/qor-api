<?php

namespace Tests\Unit\Domain\Event\UseCase;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Event\Genre;
use QOR\App\Domain\Event\GenreRepository;
use QOR\App\Domain\Event\UseCase\ActivateGenre;

class ActivateGenreTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_GIVEN_an_inactive_genre_WHEN_activating_THEN_it_becomes_active(): void
    {
        $genre = new Genre(id: 1, name: 'Rock', slug: 'rock', isActive: false);

        $repository = Mockery::mock(GenreRepository::class);
        $repository->shouldReceive('findById')->once()->with(1)->andReturn($genre);
        $repository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Genre $g) => $g->id === 1 && $g->isActive === true))
            ->andReturnUsing(fn (Genre $g) => $g);

        $useCase = new ActivateGenre($repository);

        $result = $useCase->execute(1);

        $this->assertTrue($result->isActive);
    }
}
