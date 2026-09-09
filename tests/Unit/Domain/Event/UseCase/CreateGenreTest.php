<?php

namespace Tests\Unit\Domain\Event\UseCase;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Event\Genre;
use QOR\App\Domain\Event\GenreRepository;
use QOR\App\Domain\Event\UseCase\CreateGenre;

class CreateGenreTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_GIVEN_a_valid_name_WHEN_creating_THEN_it_is_persisted_as_active(): void
    {
        $repository = Mockery::mock(GenreRepository::class);
        $repository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Genre $genre) => $genre->id === null
                && $genre->name === 'Rock'
                && $genre->isActive === true))
            ->andReturnUsing(fn (Genre $genre) => new Genre(
                id: 1,
                name: $genre->name,
                slug: 'rock',
                isActive: $genre->isActive,
            ));

        $useCase = new CreateGenre($repository);

        $result = $useCase->execute('Rock');

        $this->assertSame(1, $result->id);
        $this->assertTrue($result->isActive);
    }
}
