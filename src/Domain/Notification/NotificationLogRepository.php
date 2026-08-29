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

    /**
     * Cross-trigger consolidation check (ARCHITECTURE.md §6.1 step 2, resolved decision
     * in notifications/design.md line 30): true if ANY trigger already sent to this fan
     * for this event within the last `$windowMinutes`. Only meaningful when $eventId is
     * not null — event-less triggers have nothing to consolidate against.
     */
    public function hasRecentSendForEvent(int $userId, int $eventId, int $windowMinutes): bool;

    public function record(
        int $userId,
        NotificationTriggerType $triggerType,
        NotificationChannel $channel,
        ?int $eventId = null,
    ): NotificationLog;
}
