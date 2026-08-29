<?php

namespace Tests\Unit\Domain\Notification;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Notification\NotificationPreference;

final class NotificationPreferenceTest extends TestCase
{
    public function test_GIVEN_a_valid_user_id_WHEN_creating_default_preferences_THEN_every_channel_and_trigger_is_enabled_and_silence_is_off(): void
    {
        $preference = new NotificationPreference(id: null, userId: 1);

        $this->assertTrue($preference->pushEnabled);
        $this->assertTrue($preference->emailEnabled);
        $this->assertFalse($preference->silenceAll);
        $this->assertTrue($preference->triggerNearbyReminder);
        $this->assertTrue($preference->triggerEventChangedCancelled);
        $this->assertTrue($preference->triggerFriendInterest);
        $this->assertTrue($preference->triggerNewRegional);
    }

    public function test_GIVEN_a_non_positive_user_id_WHEN_creating_preferences_THEN_it_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NotificationPreference(id: null, userId: 0);
    }
}
