<?php

namespace QOR\App\Domain\Notification\Enum;

enum NotificationChannel: string
{
    case Push = 'push';
    case Email = 'email';
}
