<?php

namespace QOR\App\Domain\Notification\UseCase;

use QOR\App\Domain\Notification\NotificationPreference;
use QOR\App\Domain\Notification\NotificationPreferenceRepository;

final class UpdateNotificationPreference
{
    public function __construct(
        private readonly NotificationPreferenceRepository $preferences,
    ) {
    }

    public function execute(NotificationPreference $preference): NotificationPreference
    {
        return $this->preferences->save($preference);
    }
}
