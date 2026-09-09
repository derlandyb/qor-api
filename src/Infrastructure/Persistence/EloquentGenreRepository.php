<?php

namespace QOR\App\Infrastructure\Persistence;

use Illuminate\Support\Str;
use QOR\App\Domain\Event\Genre;
use QOR\App\Domain\Event\GenreRepository;
use QOR\App\Infrastructure\Persistence\Eloquent\GenreModel;

class EloquentGenreRepository implements GenreRepository
{
    public function findNameById(int $id): string
    {
        return GenreModel::findOrFail($id)->name;
    }

    public function findById(int $id): ?Genre
    {
        $model = GenreModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findAll(): array
    {
        /** @var list<Genre> $genres */
        $genres = GenreModel::orderBy('name')
            ->get()
            ->map(fn (GenreModel $model): Genre => $this->toDomain($model))
            ->values()
            ->all();

        return $genres;
    }

    public function save(Genre $genre): Genre
    {
        $model = $genre->id !== null ? GenreModel::findOrFail($genre->id) : new GenreModel();

        $model->fill([
            'name' => $genre->name,
            'slug' => Str::slug($genre->name),
            'is_active' => $genre->isActive,
        ]);

        $model->save();

        return $this->toDomain($model);
    }

    private function toDomain(GenreModel $model): Genre
    {
        return new Genre(
            id: $model->id,
            name: $model->name,
            slug: $model->slug,
            isActive: (bool) $model->is_active,
        );
    }
}
