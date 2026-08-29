<?php

namespace QOR\App\Domain\Social\UseCase;

use DomainException;
use QOR\App\Domain\Notification\Enum\NotificationTriggerType;
use QOR\App\Domain\Notification\NotificationDispatcher;
use QOR\App\Domain\Social\Enum\FriendshipStatus;
use QOR\App\Domain\Social\FriendshipRepository;

final class ShareEvent
{
    public function __construct(
        private readonly FriendshipRepository $friendships,
        private readonly NotificationDispatcher $dispatcher,
    ) {
    }

    /**
     * In-app share to a friend (FAV-21–FAV-23). Native/device sharing is
     * client-side only and doesn't call this use case. Sharing is treated as
     * a form of expressing interest to a friend, so it reuses the
     * FriendInterest trigger rather than a distinct one — the design docs
     * don't define a separate share trigger.
     */
    public function shareToFriend(int $sharerId, int $friendId, int $eventId): void
    {
        $friendship = $this->friendships->findBetween($sharerId, $friendId);

        if ($friendship === null || $friendship->status !== FriendshipStatus::Accepted) {
            throw new DomainException('Você não é mais amigo desta pessoa.');
        }

        $this->dispatcher->dispatch(
            $friendId,
            NotificationTriggerType::FriendInterest,
            $eventId,
            ['sharedByUserId' => $sharerId, 'eventId' => $eventId],
        );
    }
}
