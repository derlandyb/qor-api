<?php

namespace QOR\App\Domain\Social;

use QOR\App\Domain\Social\Enum\FavoriteState;

interface FavoriteRepository
{
    /**
     * Idempotent toggle (FAV-01/FAV-02): creates the favorite if absent, removes
     * it if present.
     */
    public function toggle(int $userId, int $eventId): FavoriteState;

    /**
     * The fan's favorited events, joined against current Event.status so a
     * favorite whose event is no longer Published is filtered out (FAV-04),
     * cursor-paginated per `config('qor.pagination.public_page_size')`.
     */
    public function listForUser(int $userId, ?string $cursor): FavoritePage;
}
