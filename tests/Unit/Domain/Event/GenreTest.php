<?php

namespace Tests\Unit\Domain\Event;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Event\Genre;

class GenreTest extends TestCase
{
    public function test_GIVEN_valid_fields_WHEN_constructing_THEN_the_genre_is_created(): void
    {
        $genre = new Genre(
            id: 1,
            name: 'Rock',
            slug: 'rock',
        );

        $this->assertSame('Rock', $genre->name);
        $this->assertTrue($genre->isActive);
    }

    public function test_GIVEN_an_empty_name_WHEN_constructing_THEN_it_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O nome do gênero não pode ser vazio.');

        new Genre(
            id: 1,
            name: '',
            slug: 'rock',
        );
    }
}
