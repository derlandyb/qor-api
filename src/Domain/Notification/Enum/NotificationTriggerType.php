<?php

namespace QOR\App\Domain\Notification\Enum;

enum NotificationTriggerType: string
{
    case NearbyReminder = 'nearby_reminder';
    case EventChanged = 'event_changed';
    case EventCancelled = 'event_cancelled';
    case FriendInterest = 'friend_interest';
    case NewRegional = 'new_regional';
}
