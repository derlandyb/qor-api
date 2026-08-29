<?php

namespace QOR\App\Domain\Notification\UseCase;

use QOR\App\Domain\Notification\Enum\NotificationTriggerType;
use QOR\App\Domain\Notification\NotificationDispatcher;
use QOR\App\Domain\Social\FavoriteRepository;

/**
 * Event-driven handler (NOTIF-05–NOTIF-08): notifies every fan who favorited
 * $eventId when it materially changes or is cancelled. Standalone for now —
 * not yet wired to an actual domain-event emission from EditEvent/CancelEvent
 * (that's T86, a later phase).
 */
final class HandleEventChangedOrCancelled
{
    public function __construct(
        private readonly FavoriteRepository $favorites,
        private readonly NotificationDispatcher $dispatcher,
    ) {
    }

    public function handle(int $eventId, NotificationTriggerType $changeType): void
    {
        foreach ($this->favorites->listUserIdsWhoFavorited($eventId) as $userId) {
            $this->dispatcher->dispatch($userId, $changeType, $eventId, ['eventId' => $eventId]);
        }
    }
}
