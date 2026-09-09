<?php

namespace Tests\Unit\Domain\Event\UseCase;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Event\Genre;
use QOR\App\Domain\Event\GenreRepository;
use QOR\App\Domain\Event\UseCase\ListAllGenres;

class ListAllGenresTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_GIVEN_active_and_inactive_genres_WHEN_listing_all_THEN_both_are_returned(): void
    {
        $genres = [
            new Genre(id: 1, name: 'Rock', slug: 'rock', isActive: true),
            new Genre(id: 2, name: 'Samba', slug: 'samba', isActive: false),
        ];

        $repository = Mockery::mock(GenreRepository::class);
        $repository->shouldReceive('findAll')->once()->andReturn($genres);

        $useCase = new ListAllGenres($repository);

        $this->assertSame($genres, $useCase->execute());
    }
}
