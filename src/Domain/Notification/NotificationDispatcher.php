<?php

namespace QOR\App\Domain\Notification;

use QOR\App\Domain\Notification\Enum\NotificationChannel;
use QOR\App\Domain\Notification\Enum\NotificationTriggerType;

/**
 * Central notification send pipeline (ARCHITECTURE.md §6.1) — the only call
 * site that reads NotificationPreference, checks NotificationLog for
 * dedup/consolidation, and invokes a NotificationSender per enabled channel.
 */
final class NotificationDispatcher
{
    public function __construct(
        private readonly NotificationPreferenceRepository $preferences,
        private readonly NotificationLogRepository $logs,
        private readonly NotificationSender $pushSender,
        private readonly NotificationSender $emailSender,
        private readonly int $consolidationWindowMinutes = 60,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function dispatch(int $userId, NotificationTriggerType $triggerType, ?int $eventId, array $payload): void
    {
        $preference = $this->preferences->findByUserId($userId)
            ?? new NotificationPreference(id: null, userId: $userId);

        if ($preference->silenceAll) {
            return;
        }

        $alwaysFires = $triggerType === NotificationTriggerType::EventChanged
            || $triggerType === NotificationTriggerType::EventCancelled;

        if (! $alwaysFires && ! $this->isTriggerEnabled($preference, $triggerType)) {
            return;
        }

        if ($this->logs->hasBeenSent($userId, $triggerType, $eventId)) {
            return;
        }

        if ($eventId !== null && $this->logs->hasRecentSendForEvent($userId, $eventId, $this->consolidationWindowMinutes)) {
            return;
        }

        if ($preference->pushEnabled) {
            $this->pushSender->send($userId, $payload);
            $this->logs->record($userId, $triggerType, NotificationChannel::Push, $eventId);
        }

        if ($preference->emailEnabled) {
            $this->emailSender->send($userId, $payload);
            $this->logs->record($userId, $triggerType, NotificationChannel::Email, $eventId);
        }
    }

    private function isTriggerEnabled(NotificationPreference $preference, NotificationTriggerType $triggerType): bool
    {
        return match ($triggerType) {
            NotificationTriggerType::NearbyReminder => $preference->triggerNearbyReminder,
            NotificationTriggerType::FriendInterest => $preference->triggerFriendInterest,
            NotificationTriggerType::NewRegional => $preference->triggerNewRegional,
            NotificationTriggerType::EventChanged, NotificationTriggerType::EventCancelled => $preference->triggerEventChangedCancelled,
        };
    }
}
