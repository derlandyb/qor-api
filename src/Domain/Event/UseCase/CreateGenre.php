<?php

namespace QOR\App\Domain\Event\UseCase;

use QOR\App\Domain\Event\Genre;
use QOR\App\Domain\Event\GenreRepository;

final class CreateGenre
{
    public function __construct(
        private readonly GenreRepository $genres,
    ) {
    }

    public function execute(string $name): Genre
    {
        return $this->genres->save(new Genre(
            id: null,
            name: $name,
            slug: null,
            isActive: true,
        ));
    }
}
