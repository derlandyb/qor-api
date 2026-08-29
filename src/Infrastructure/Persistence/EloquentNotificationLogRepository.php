<?php

namespace QOR\App\Infrastructure\Persistence;

use QOR\App\Domain\Notification\Enum\NotificationChannel;
use QOR\App\Domain\Notification\Enum\NotificationTriggerType;
use QOR\App\Domain\Notification\NotificationLog;
use QOR\App\Domain\Notification\NotificationLogRepository;
use QOR\App\Infrastructure\Persistence\Eloquent\NotificationLogModel;

class EloquentNotificationLogRepository implements NotificationLogRepository
{
    public function hasBeenSent(int $userId, NotificationTriggerType $triggerType, ?int $eventId = null): bool
    {
        return NotificationLogModel::where('user_id', $userId)
            ->where('trigger_type', $triggerType->value)
            ->where('event_id', $eventId)
            ->exists();
    }

    public function hasRecentSendForEvent(int $userId, int $eventId, int $windowMinutes): bool
    {
        return NotificationLogModel::where('user_id', $userId)
            ->where('event_id', $eventId)
            ->where('sent_at', '>=', now()->subMinutes($windowMinutes))
            ->exists();
    }

    public function record(
        int $userId,
        NotificationTriggerType $triggerType,
        NotificationChannel $channel,
        ?int $eventId = null,
    ): NotificationLog {
        $model = NotificationLogModel::create([
            'user_id' => $userId,
            'trigger_type' => $triggerType->value,
            'event_id' => $eventId,
            'channel' => $channel->value,
            'sent_at' => now(),
        ]);

        return new NotificationLog(
            id: $model->id,
            userId: $model->user_id,
            triggerType: $model->trigger_type,
            channel: $model->channel,
            eventId: $model->event_id,
            sentAt: $model->sent_at->toDateTimeImmutable(),
        );
    }
}
