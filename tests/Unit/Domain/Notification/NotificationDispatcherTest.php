<?php

namespace Tests\Unit\Domain\Notification;

use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Notification\Enum\NotificationChannel;
use QOR\App\Domain\Notification\Enum\NotificationTriggerType;
use QOR\App\Domain\Notification\NotificationDispatcher;
use QOR\App\Domain\Notification\NotificationLog;
use QOR\App\Domain\Notification\NotificationLogRepository;
use QOR\App\Domain\Notification\NotificationPreference;
use QOR\App\Domain\Notification\NotificationPreferenceRepository;
use QOR\App\Domain\Notification\NotificationSender;

final class InMemoryNotificationPreferenceRepository implements NotificationPreferenceRepository
{
    /** @var array<int, NotificationPreference> */
    private array $preferences = [];

    public function findByUserId(int $userId): ?NotificationPreference
    {
        return $this->preferences[$userId] ?? null;
    }

    public function save(NotificationPreference $preference): NotificationPreference
    {
        $this->preferences[$preference->userId] = $preference;

        return $preference;
    }
}

final class InMemoryNotificationLogRepository implements NotificationLogRepository
{
    /** @var list<array{userId: int, triggerType: NotificationTriggerType, eventId: ?int}> */
    private array $sent = [];

    public function hasBeenSent(int $userId, NotificationTriggerType $triggerType, ?int $eventId = null): bool
    {
        foreach ($this->sent as $entry) {
            if ($entry['userId'] === $userId && $entry['triggerType'] === $triggerType && $entry['eventId'] === $eventId) {
                return true;
            }
        }

        return false;
    }

    public function record(
        int $userId,
        NotificationTriggerType $triggerType,
        NotificationChannel $channel,
        ?int $eventId = null,
    ): NotificationLog {
        $this->sent[] = ['userId' => $userId, 'triggerType' => $triggerType, 'eventId' => $eventId];

        return new NotificationLog(id: null, userId: $userId, triggerType: $triggerType, channel: $channel, eventId: $eventId);
    }
}

final class SpyNotificationSender implements NotificationSender
{
    /** @var list<array{userId: int, payload: array<string, mixed>}> */
    public array $calls = [];

    public function send(int $userId, array $payload): void
    {
        $this->calls[] = ['userId' => $userId, 'payload' => $payload];
    }
}

final class NotificationDispatcherTest extends TestCase
{
    private InMemoryNotificationPreferenceRepository $preferences;
    private InMemoryNotificationLogRepository $logs;
    private SpyNotificationSender $pushSender;
    private SpyNotificationSender $emailSender;
    private NotificationDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->preferences = new InMemoryNotificationPreferenceRepository();
        $this->logs = new InMemoryNotificationLogRepository();
        $this->pushSender = new SpyNotificationSender();
        $this->emailSender = new SpyNotificationSender();
        $this->dispatcher = new NotificationDispatcher($this->preferences, $this->logs, $this->pushSender, $this->emailSender);
    }

    public function test_GIVEN_global_silence_enabled_WHEN_dispatching_any_trigger_THEN_no_channel_receives_a_send(): void
    {
        $this->preferences->save(new NotificationPreference(id: null, userId: 1, silenceAll: true));

        $this->dispatcher->dispatch(1, NotificationTriggerType::NearbyReminder, 10, []);

        $this->assertCount(0, $this->pushSender->calls);
        $this->assertCount(0, $this->emailSender->calls);
    }

    public function test_GIVEN_push_disabled_WHEN_dispatching_THEN_only_email_receives_a_send(): void
    {
        $this->preferences->save(new NotificationPreference(id: null, userId: 1, pushEnabled: false));

        $this->dispatcher->dispatch(1, NotificationTriggerType::NearbyReminder, 10, []);

        $this->assertCount(0, $this->pushSender->calls);
        $this->assertCount(1, $this->emailSender->calls);
    }

    public function test_GIVEN_email_disabled_WHEN_dispatching_THEN_only_push_receives_a_send(): void
    {
        $this->preferences->save(new NotificationPreference(id: null, userId: 1, emailEnabled: false));

        $this->dispatcher->dispatch(1, NotificationTriggerType::NearbyReminder, 10, []);

        $this->assertCount(1, $this->pushSender->calls);
        $this->assertCount(0, $this->emailSender->calls);
    }

    public function test_GIVEN_the_nearby_reminder_trigger_is_disabled_WHEN_dispatching_it_THEN_no_channel_receives_a_send(): void
    {
        $this->preferences->save(new NotificationPreference(id: null, userId: 1, triggerNearbyReminder: false));

        $this->dispatcher->dispatch(1, NotificationTriggerType::NearbyReminder, 10, []);

        $this->assertCount(0, $this->pushSender->calls);
        $this->assertCount(0, $this->emailSender->calls);
    }

    public function test_GIVEN_the_friend_interest_trigger_is_disabled_WHEN_dispatching_it_THEN_no_channel_receives_a_send(): void
    {
        $this->preferences->save(new NotificationPreference(id: null, userId: 1, triggerFriendInterest: false));

        $this->dispatcher->dispatch(1, NotificationTriggerType::FriendInterest, 10, []);

        $this->assertCount(0, $this->pushSender->calls);
        $this->assertCount(0, $this->emailSender->calls);
    }

    public function test_GIVEN_event_changed_cancelled_toggle_is_disabled_WHEN_dispatching_an_event_changed_trigger_THEN_it_still_sends(): void
    {
        $this->preferences->save(new NotificationPreference(id: null, userId: 1, triggerEventChangedCancelled: false));

        $this->dispatcher->dispatch(1, NotificationTriggerType::EventChanged, 10, []);

        $this->assertCount(1, $this->pushSender->calls);
        $this->assertCount(1, $this->emailSender->calls);
    }

    public function test_GIVEN_event_cancelled_toggle_is_disabled_WHEN_dispatching_an_event_cancelled_trigger_THEN_it_still_sends(): void
    {
        $this->preferences->save(new NotificationPreference(id: null, userId: 1, triggerEventChangedCancelled: false));

        $this->dispatcher->dispatch(1, NotificationTriggerType::EventCancelled, 10, []);

        $this->assertCount(1, $this->pushSender->calls);
        $this->assertCount(1, $this->emailSender->calls);
    }

    public function test_GIVEN_global_silence_enabled_WHEN_dispatching_an_event_changed_trigger_THEN_it_does_not_send(): void
    {
        $this->preferences->save(new NotificationPreference(id: null, userId: 1, silenceAll: true));

        $this->dispatcher->dispatch(1, NotificationTriggerType::EventChanged, 10, []);

        $this->assertCount(0, $this->pushSender->calls);
        $this->assertCount(0, $this->emailSender->calls);
    }

    public function test_GIVEN_a_duplicate_trigger_for_the_same_fan_and_event_WHEN_dispatching_twice_THEN_it_consolidates_to_a_single_send(): void
    {
        $this->dispatcher->dispatch(1, NotificationTriggerType::NearbyReminder, 10, []);
        $this->dispatcher->dispatch(1, NotificationTriggerType::NearbyReminder, 10, []);

        $this->assertCount(1, $this->pushSender->calls);
        $this->assertCount(1, $this->emailSender->calls);
    }

    public function test_GIVEN_a_preference_change_between_calls_WHEN_dispatching_again_THEN_the_preference_is_read_fresh_not_cached(): void
    {
        $this->preferences->save(new NotificationPreference(id: null, userId: 1));
        $this->dispatcher->dispatch(1, NotificationTriggerType::NearbyReminder, 10, []);

        $this->preferences->save(new NotificationPreference(id: null, userId: 1, silenceAll: true));
        $this->dispatcher->dispatch(1, NotificationTriggerType::NearbyReminder, 11, []);

        $this->assertCount(1, $this->pushSender->calls);
        $this->assertCount(1, $this->emailSender->calls);
    }

    public function test_GIVEN_no_preference_row_exists_WHEN_dispatching_THEN_default_preferences_allow_the_send(): void
    {
        $this->dispatcher->dispatch(1, NotificationTriggerType::NewRegional, null, []);

        $this->assertCount(1, $this->pushSender->calls);
        $this->assertCount(1, $this->emailSender->calls);
    }
}
