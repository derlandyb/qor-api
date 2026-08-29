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
use QOR\App\Domain\Notification\UseCase\HandleFriendInterest;
use QOR\App\Domain\Social\Enum\FriendshipStatus;
use QOR\App\Domain\Social\FriendPage;
use QOR\App\Domain\Social\Friendship;
use QOR\App\Domain\Social\FriendshipRepository;

final class InMemoryNotificationPreferenceRepositoryForFriendInterest implements NotificationPreferenceRepository
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

final class InMemoryNotificationLogRepositoryForFriendInterest implements NotificationLogRepository
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

final class SpyNotificationSenderForFriendInterest implements NotificationSender
{
    /** @var list<array{userId: int, payload: array<string, mixed>}> */
    public array $calls = [];

    public function send(int $userId, array $payload): void
    {
        $this->calls[] = ['userId' => $userId, 'payload' => $payload];
    }
}

final class HandleFriendInterestTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function dispatcher(SpyNotificationSenderForFriendInterest $push, SpyNotificationSenderForFriendInterest $email, ?NotificationPreferenceRepository $preferences = null): NotificationDispatcher
    {
        return new NotificationDispatcher(
            $preferences ?? new InMemoryNotificationPreferenceRepositoryForFriendInterest(),
            new InMemoryNotificationLogRepositoryForFriendInterest(),
            $push,
            $email,
        );
    }

    public function test_GIVEN_two_mutual_friends_WHEN_handling_a_new_favorite_THEN_both_friends_are_notified(): void
    {
        $friendships = Mockery::mock(FriendshipRepository::class);
        $friendships->shouldReceive('listAccepted')->once()->with(1, null)->andReturn(new FriendPage(
            items: [
                new Friendship(id: 1, requesterId: 1, recipientId: 2, status: FriendshipStatus::Accepted),
                new Friendship(id: 2, requesterId: 3, recipientId: 1, status: FriendshipStatus::Accepted),
            ],
            nextCursor: null,
        ));

        $push = new SpyNotificationSenderForFriendInterest();
        $email = new SpyNotificationSenderForFriendInterest();

        $useCase = new HandleFriendInterest($friendships, $this->dispatcher($push, $email));

        $useCase->handle(1, 10);

        $this->assertCount(2, $push->calls);
        $this->assertSame([2, 3], array_column($push->calls, 'userId'));
    }

    public function test_GIVEN_a_friend_disabled_the_friend_interest_trigger_WHEN_handling_a_new_favorite_THEN_that_friend_is_not_notified(): void
    {
        $preferences = new InMemoryNotificationPreferenceRepositoryForFriendInterest();
        $preferences->save(new NotificationPreference(id: null, userId: 2, triggerFriendInterest: false));

        $friendships = Mockery::mock(FriendshipRepository::class);
        $friendships->shouldReceive('listAccepted')->once()->with(1, null)->andReturn(new FriendPage(
            items: [new Friendship(id: 1, requesterId: 1, recipientId: 2, status: FriendshipStatus::Accepted)],
            nextCursor: null,
        ));

        $push = new SpyNotificationSenderForFriendInterest();
        $email = new SpyNotificationSenderForFriendInterest();

        $useCase = new HandleFriendInterest($friendships, $this->dispatcher($push, $email, $preferences));

        $useCase->handle(1, 10);

        $this->assertCount(0, $push->calls);
    }

    public function test_GIVEN_no_friends_WHEN_handling_a_new_favorite_THEN_no_notification_is_sent(): void
    {
        $friendships = Mockery::mock(FriendshipRepository::class);
        $friendships->shouldReceive('listAccepted')->once()->with(1, null)->andReturn(new FriendPage(items: [], nextCursor: null));

        $push = new SpyNotificationSenderForFriendInterest();
        $email = new SpyNotificationSenderForFriendInterest();

        $useCase = new HandleFriendInterest($friendships, $this->dispatcher($push, $email));

        $useCase->handle(1, 10);

        $this->assertCount(0, $push->calls);
    }
}
