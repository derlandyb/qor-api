<?php

namespace Tests\Unit\Domain\Event\UseCase;

use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Event\Genre;
use QOR\App\Domain\Event\GenreRepository;
use QOR\App\Domain\Event\UseCase\UpdateGenre;

class UpdateGenreTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_GIVEN_an_existing_genre_WHEN_updating_its_name_THEN_it_is_persisted_with_the_new_name(): void
    {
        $genre = new Genre(id: 1, name: 'Rock', slug: 'rock', isActive: true);

        $repository = Mockery::mock(GenreRepository::class);
        $repository->shouldReceive('findById')->once()->with(1)->andReturn($genre);
        $repository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Genre $g) => $g->id === 1 && $g->name === 'Rock Nacional' && $g->isActive === true))
            ->andReturnUsing(fn (Genre $g) => $g);

        $useCase = new UpdateGenre($repository);

        $result = $useCase->execute(1, 'Rock Nacional');

        $this->assertSame('Rock Nacional', $result->name);
    }

    public function test_GIVEN_a_non_existent_genre_WHEN_updating_THEN_it_throws_invalid_argument_exception(): void
    {
        $repository = Mockery::mock(GenreRepository::class);
        $repository->shouldReceive('findById')->once()->with(999)->andReturn(null);

        $useCase = new UpdateGenre($repository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Gênero não encontrado.');

        $useCase->execute(999, 'Rock');
    }
}
