<?php

namespace Tests\Unit\Domain\Notification\UseCase;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Notification\Enum\NotificationChannel;
use QOR\App\Domain\Notification\Enum\NotificationTriggerType;
use QOR\App\Domain\Notification\NotificationDispatcher;
use QOR\App\Domain\Notification\NotificationLog;
use QOR\App\Domain\Notification\NotificationLogRepository;
use QOR\App\Domain\Notification\NotificationPreference;
use QOR\App\Domain\Notification\NotificationPreferenceRepository;
use QOR\App\Domain\Notification\NotificationSender;
use QOR\App\Domain\Notification\UseCase\HandleEventChangedOrCancelled;
use QOR\App\Domain\Social\FavoriteRepository;

final class InMemoryNotificationPreferenceRepositoryForEventChange implements NotificationPreferenceRepository
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

final class InMemoryNotificationLogRepositoryForEventChange implements NotificationLogRepository
{
    public function hasBeenSent(int $userId, NotificationTriggerType $triggerType, ?int $eventId = null): bool
    {
        return false;
    }

    public function hasRecentSendForEvent(int $userId, int $eventId, int $windowMinutes): bool
    {
        return false;
    }

    public function record(int $userId, NotificationTriggerType $triggerType, NotificationChannel $channel, ?int $eventId = null): NotificationLog
    {
        return new NotificationLog(id: null, userId: $userId, triggerType: $triggerType, channel: $channel, eventId: $eventId);
    }
}

final class SpyNotificationSenderForEventChange implements NotificationSender
{
    /** @var list<array{userId: int, payload: array<string, mixed>}> */
    public array $calls = [];

    public function send(int $userId, array $payload): void
    {
        $this->calls[] = ['userId' => $userId, 'payload' => $payload];
    }
}

final class HandleEventChangedOrCancelledTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function dispatcher(SpyNotificationSenderForEventChange $push, SpyNotificationSenderForEventChange $email, ?NotificationPreferenceRepository $preferences = null): NotificationDispatcher
    {
        return new NotificationDispatcher(
            $preferences ?? new InMemoryNotificationPreferenceRepositoryForEventChange(),
            new InMemoryNotificationLogRepositoryForEventChange(),
            $push,
            $email,
        );
    }

    public function test_GIVEN_two_fans_favorited_the_event_WHEN_handling_an_event_change_THEN_both_are_notified(): void
    {
        $favorites = Mockery::mock(FavoriteRepository::class);
        $favorites->shouldReceive('listUserIdsWhoFavorited')->once()->with(10)->andReturn([1, 2]);

        $push = new SpyNotificationSenderForEventChange();
        $email = new SpyNotificationSenderForEventChange();

        $useCase = new HandleEventChangedOrCancelled($favorites, $this->dispatcher($push, $email));

        $useCase->handle(10, NotificationTriggerType::EventChanged);

        $this->assertCount(2, $push->calls);
    }

    public function test_GIVEN_a_fan_disabled_the_event_changed_cancelled_toggle_WHEN_handling_a_cancellation_THEN_it_still_fires(): void
    {
        $preferences = new InMemoryNotificationPreferenceRepositoryForEventChange();
        $preferences->save(new NotificationPreference(id: null, userId: 1, triggerEventChangedCancelled: false));

        $favorites = Mockery::mock(FavoriteRepository::class);
        $favorites->shouldReceive('listUserIdsWhoFavorited')->once()->with(10)->andReturn([1]);

        $push = new SpyNotificationSenderForEventChange();
        $email = new SpyNotificationSenderForEventChange();

        $useCase = new HandleEventChangedOrCancelled($favorites, $this->dispatcher($push, $email, $preferences));

        $useCase->handle(10, NotificationTriggerType::EventCancelled);

        $this->assertCount(1, $push->calls);
    }

    public function test_GIVEN_no_fan_favorited_the_event_WHEN_handling_a_change_THEN_no_notification_is_sent(): void
    {
        $favorites = Mockery::mock(FavoriteRepository::class);
        $favorites->shouldReceive('listUserIdsWhoFavorited')->once()->with(10)->andReturn([]);

        $push = new SpyNotificationSenderForEventChange();
        $email = new SpyNotificationSenderForEventChange();

        $useCase = new HandleEventChangedOrCancelled($favorites, $this->dispatcher($push, $email));

        $useCase->handle(10, NotificationTriggerType::EventChanged);

        $this->assertCount(0, $push->calls);
    }
}
