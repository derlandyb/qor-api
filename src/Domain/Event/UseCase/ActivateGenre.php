<?php

namespace QOR\App\Domain\Event\UseCase;

use InvalidArgumentException;
use QOR\App\Domain\Event\Genre;
use QOR\App\Domain\Event\GenreRepository;

final class ActivateGenre
{
    public function __construct(
        private readonly GenreRepository $genres,
    ) {
    }

    public function execute(int $genreId): Genre
    {
        $genre = $this->genres->findById($genreId);

        if ($genre === null) {
            throw new InvalidArgumentException('Gênero não encontrado.');
        }

        return $this->genres->save(new Genre(
            id: $genre->id,
            name: $genre->name,
            slug: $genre->slug,
            isActive: true,
        ));
    }
}
