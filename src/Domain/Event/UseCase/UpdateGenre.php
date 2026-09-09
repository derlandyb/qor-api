<?php

namespace QOR\App\Domain\Event\UseCase;

use InvalidArgumentException;
use QOR\App\Domain\Event\Genre;
use QOR\App\Domain\Event\GenreRepository;

final class UpdateGenre
{
    public function __construct(
        private readonly GenreRepository $genres,
    ) {
    }

    public function execute(int $genreId, string $name): Genre
    {
        $genre = $this->genres->findById($genreId);

        if ($genre === null) {
            throw new InvalidArgumentException('Gênero não encontrado.');
        }

        return $this->genres->save(new Genre(
            id: $genre->id,
            name: $name,
            slug: null,
            isActive: $genre->isActive,
        ));
    }
}
