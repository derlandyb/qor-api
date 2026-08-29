<?php

namespace QOR\App\Domain\Notification;

use InvalidArgumentException;

final class NotificationPreference
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly bool $pushEnabled = true,
        public readonly bool $emailEnabled = true,
        public readonly bool $silenceAll = false,
        public readonly bool $triggerNearbyReminder = true,
        public readonly bool $triggerEventChangedCancelled = true,
        public readonly bool $triggerFriendInterest = true,
        public readonly bool $triggerNewRegional = true,
    ) {
        if ($this->userId <= 0) {
            throw new InvalidArgumentException('O ID do usuário deve ser válido.');
        }
    }
}
