<?php

namespace Tests\Unit\Domain\Notification;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Notification\Enum\NotificationChannel;
use QOR\App\Domain\Notification\Enum\NotificationTriggerType;
use QOR\App\Domain\Notification\NotificationLog;

final class NotificationLogTest extends TestCase
{
    public function test_GIVEN_a_valid_user_id_WHEN_creating_a_log_entry_THEN_it_is_constructed_successfully(): void
    {
        $log = new NotificationLog(
            id: null,
            userId: 1,
            triggerType: NotificationTriggerType::NearbyReminder,
            channel: NotificationChannel::Push,
            eventId: 5,
        );

        $this->assertSame(NotificationTriggerType::NearbyReminder, $log->triggerType);
        $this->assertSame(NotificationChannel::Push, $log->channel);
        $this->assertSame(5, $log->eventId);
    }

    public function test_GIVEN_a_new_regional_digest_WHEN_creating_a_log_entry_THEN_the_event_id_may_be_null(): void
    {
        $log = new NotificationLog(
            id: null,
            userId: 1,
            triggerType: NotificationTriggerType::NewRegional,
            channel: NotificationChannel::Email,
        );

        $this->assertNull($log->eventId);
    }

    public function test_GIVEN_a_non_positive_user_id_WHEN_creating_a_log_entry_THEN_it_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NotificationLog(
            id: null,
            userId: 0,
            triggerType: NotificationTriggerType::NearbyReminder,
            channel: NotificationChannel::Push,
        );
    }
}
