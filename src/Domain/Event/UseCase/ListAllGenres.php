<?php

namespace QOR\App\Domain\Event\UseCase;

use QOR\App\Domain\Event\GenreRepository;

final class ListAllGenres
{
    public function __construct(
        private readonly GenreRepository $genres,
    ) {
    }

    /**
     * @return list<\QOR\App\Domain\Event\Genre>
     */
    public function execute(): array
    {
        return $this->genres->findAll();
    }
}
