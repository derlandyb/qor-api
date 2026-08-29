<?php

namespace QOR\App\Domain\Notification\UseCase;

use QOR\App\Domain\Notification\Enum\NotificationTriggerType;
use QOR\App\Domain\Notification\NotificationDispatcher;
use QOR\App\Domain\Social\FriendshipRepository;

/**
 * Event-driven handler (NOTIF-13–NOTIF-15): notifies the mutual friends of
 * $favoritedByUserId when they favorite $eventId. Standalone for now — not
 * yet wired to an actual FavoriteCreated domain event from ToggleFavorite
 * (that's T87, a later phase).
 */
final class HandleFriendInterest
{
    public function __construct(
        private readonly FriendshipRepository $friendships,
        private readonly NotificationDispatcher $dispatcher,
    ) {
    }

    public function handle(int $favoritedByUserId, int $eventId): void
    {
        $friendPage = $this->friendships->listAccepted($favoritedByUserId, null);

        foreach ($friendPage->items as $friendship) {
            $friendUserId = $friendship->requesterId === $favoritedByUserId
                ? $friendship->recipientId
                : $friendship->requesterId;

            $this->dispatcher->dispatch(
                $friendUserId,
                NotificationTriggerType::FriendInterest,
                $eventId,
                ['favoritedByUserId' => $favoritedByUserId, 'eventId' => $eventId],
            );
        }
    }
}
