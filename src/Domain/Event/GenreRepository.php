<?php

namespace QOR\App\Domain\Event;

interface GenreRepository
{
    /**
     * Resolves a genre's display name by id. Genre is a DB-backed lookup
     * table referenced by id (ARCHITECTURE.md §14.1) — this is the only
     * place that name resolution happens, so use cases never have to know
     * about the storage detail behind it.
     */
    public function findNameById(int $id): string;
}
