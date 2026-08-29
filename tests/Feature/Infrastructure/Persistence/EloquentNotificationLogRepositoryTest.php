<?php

namespace Tests\Feature\Infrastructure\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use QOR\App\Domain\Notification\Enum\NotificationChannel;
use QOR\App\Domain\Notification\Enum\NotificationTriggerType;
use QOR\App\Infrastructure\Persistence\Eloquent\EventModel;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;
use QOR\App\Infrastructure\Persistence\EloquentNotificationLogRepository;
use Tests\TestCase;

class EloquentNotificationLogRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_no_prior_send_WHEN_checking_has_been_sent_THEN_it_returns_false(): void
    {
        $user = UserModel::factory()->create();
        $repository = new EloquentNotificationLogRepository();

        $this->assertFalse($repository->hasBeenSent($user->id, NotificationTriggerType::NearbyReminder, 5));
    }

    public function test_GIVEN_a_recorded_send_WHEN_checking_has_been_sent_for_the_same_fan_trigger_and_event_THEN_it_returns_true(): void
    {
        $user = UserModel::factory()->create();
        $event = EventModel::factory()->create();
        $repository = new EloquentNotificationLogRepository();

        $repository->record($user->id, NotificationTriggerType::NearbyReminder, NotificationChannel::Push, $event->id);

        $this->assertTrue($repository->hasBeenSent($user->id, NotificationTriggerType::NearbyReminder, $event->id));
    }

    public function test_GIVEN_a_recorded_send_for_one_event_WHEN_checking_has_been_sent_for_a_different_event_THEN_it_returns_false(): void
    {
        $user = UserModel::factory()->create();
        $eventA = EventModel::factory()->create();
        $eventB = EventModel::factory()->create();
        $repository = new EloquentNotificationLogRepository();

        $repository->record($user->id, NotificationTriggerType::NearbyReminder, NotificationChannel::Push, $eventA->id);

        $this->assertFalse($repository->hasBeenSent($user->id, NotificationTriggerType::NearbyReminder, $eventB->id));
    }

    public function test_GIVEN_a_recorded_send_for_a_different_trigger_WHEN_checking_recent_send_for_the_same_fan_and_event_THEN_it_returns_true(): void
    {
        $user = UserModel::factory()->create();
        $event = EventModel::factory()->create();
        $repository = new EloquentNotificationLogRepository();

        $repository->record($user->id, NotificationTriggerType::NearbyReminder, NotificationChannel::Push, $event->id);

        $this->assertTrue($repository->hasRecentSendForEvent($user->id, $event->id, 60));
    }

    public function test_GIVEN_a_recorded_send_older_than_the_window_WHEN_checking_recent_send_THEN_it_returns_false(): void
    {
        $user = UserModel::factory()->create();
        $event = EventModel::factory()->create();
        $repository = new EloquentNotificationLogRepository();

        $repository->record($user->id, NotificationTriggerType::NearbyReminder, NotificationChannel::Push, $event->id);
        \QOR\App\Infrastructure\Persistence\Eloquent\NotificationLogModel::where('event_id', $event->id)
            ->update(['sent_at' => now()->subMinutes(120)]);

        $this->assertFalse($repository->hasRecentSendForEvent($user->id, $event->id, 60));
    }

    public function test_GIVEN_a_new_regional_digest_WHEN_recording_it_THEN_the_event_id_is_null(): void
    {
        $user = UserModel::factory()->create();
        $repository = new EloquentNotificationLogRepository();

        $log = $repository->record($user->id, NotificationTriggerType::NewRegional, NotificationChannel::Email);

        $this->assertNull($log->eventId);
        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $user->id,
            'trigger_type' => NotificationTriggerType::NewRegional->value,
            'event_id' => null,
        ]);
    }
}
