<?php

namespace QOR\App\Domain\Social\UseCase;

use QOR\App\Domain\Shared\DomainEventPublisher;
use QOR\App\Domain\Social\DomainEvent\FavoriteCreated;
use QOR\App\Domain\Social\Enum\FavoriteState;
use QOR\App\Domain\Social\FavoritePage;
use QOR\App\Domain\Social\FavoriteRepository;

final class ToggleFavorite
{
    public function __construct(
        private readonly FavoriteRepository $favorites,
        private readonly DomainEventPublisher $domainEvents,
    ) {
    }

    public function execute(int $userId, int $eventId): FavoriteState
    {
        $state = $this->favorites->toggle($userId, $eventId);

        if ($state === FavoriteState::Favorited) {
            $this->domainEvents->publish(new FavoriteCreated($userId, $eventId));
        }

        return $state;
    }

    public function listForUser(int $userId, ?string $cursor): FavoritePage
    {
        return $this->favorites->listForUser($userId, $cursor);
    }
}
