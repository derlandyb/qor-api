<?php

namespace QOR\App\Infrastructure\Persistence;

use QOR\App\Domain\Event\GenreRepository;
use QOR\App\Infrastructure\Persistence\Eloquent\GenreModel;

class EloquentGenreRepository implements GenreRepository
{
    public function findNameById(int $id): string
    {
        return GenreModel::findOrFail($id)->name;
    }
}
