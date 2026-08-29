<?php

namespace QOR\App\Domain\Social\UseCase;

use QOR\App\Domain\Social\Enum\FavoriteState;
use QOR\App\Domain\Social\FavoritePage;
use QOR\App\Domain\Social\FavoriteRepository;

final class ToggleFavorite
{
    public function __construct(
        private readonly FavoriteRepository $favorites,
    ) {
    }

    public function execute(int $userId, int $eventId): FavoriteState
    {
        return $this->favorites->toggle($userId, $eventId);
    }

    public function listForUser(int $userId, ?string $cursor): FavoritePage
    {
        return $this->favorites->listForUser($userId, $cursor);
    }
}
