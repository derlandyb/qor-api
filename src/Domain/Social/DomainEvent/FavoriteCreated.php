<?php

namespace QOR\App\Domain\Social\DomainEvent;

/**
 * Emitted by ToggleFavorite (T66) only when a favorite is newly created —
 * never on unfavorite (NOTIF-13). HandleFriendInterest (T79) subscribes to
 * this and notifies the favoriting fan's accepted friends.
 */
final class FavoriteCreated
{
    public function __construct(
        public readonly int $userId,
        public readonly int $eventId,
    ) {
    }
}
