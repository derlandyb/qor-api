<?php

namespace QOR\App\Domain\Notification;

interface NotificationPreferenceRepository
{
    public function findByUserId(int $userId): ?NotificationPreference;

    /**
     * Creates or updates the fan's single preference row (1:1 per ARCHITECTURE.md §4).
     */
    public function save(NotificationPreference $preference): NotificationPreference;
}
