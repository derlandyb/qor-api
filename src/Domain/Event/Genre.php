<?php

namespace QOR\App\Domain\Event;

use InvalidArgumentException;

final class Genre
{
    /**
     * $slug is nullable because it's always derived from $name by
     * EloquentGenreRepository::save() (Str::slug isn't available to the
     * domain layer, ARCHITECTURE.md §8.5) — callers pass null and read the
     * real value back off the saved entity.
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly ?string $slug,
        public readonly bool $isActive = true,
    ) {
        if ($this->name === '') {
            throw new InvalidArgumentException('O nome do gênero não pode ser vazio.');
        }
    }
}
