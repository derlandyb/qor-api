<?php

namespace QOR\App\Domain\Notification;

use DateTimeImmutable;
use InvalidArgumentException;
use QOR\App\Domain\Notification\Enum\NotificationChannel;
use QOR\App\Domain\Notification\Enum\NotificationTriggerType;

final class NotificationLog
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly NotificationTriggerType $triggerType,
        public readonly NotificationChannel $channel,
        public readonly ?int $eventId = null,
        public readonly ?DateTimeImmutable $sentAt = null,
    ) {
        if ($this->userId <= 0) {
            throw new InvalidArgumentException('O ID do usuário deve ser válido.');
        }
    }
}
