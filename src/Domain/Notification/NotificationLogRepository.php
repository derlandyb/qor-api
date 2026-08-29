<?php

namespace QOR\App\Domain\Notification;

use QOR\App\Domain\Notification\Enum\NotificationChannel;
use QOR\App\Domain\Notification\Enum\NotificationTriggerType;

interface NotificationLogRepository
{
    /**
     * Dedup/consolidation check (NOTIF-04, ARCHITECTURE.md §6.1 step 2) — true if a
     * send for this fan+trigger+event already exists.
     */
    public function hasBeenSent(int $userId, NotificationTriggerType $triggerType, ?int $eventId = null): bool;

    public function record(
        int $userId,
        NotificationTriggerType $triggerType,
        NotificationChannel $channel,
        ?int $eventId = null,
    ): NotificationLog;
}
