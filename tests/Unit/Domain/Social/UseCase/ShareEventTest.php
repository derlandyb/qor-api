<?php

namespace Tests\Unit\Domain\Social\UseCase;

use DomainException;
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
use QOR\App\Domain\Social\Enum\FriendshipStatus;
use QOR\App\Domain\Social\Friendship;
use QOR\App\Domain\Social\FriendshipRepository;
use QOR\App\Domain\Social\UseCase\ShareEvent;

final class InMemoryNotificationPreferenceRepositoryForShare implements NotificationPreferenceRepository
{
    public function findByUserId(int $userId): ?NotificationPreference
    {
        return null;
    }

    public function save(NotificationPreference $preference): NotificationPreference
    {
        return $preference;
    }
}

final class InMemoryNotificationLogRepositoryForShare implements NotificationLogRepository
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

final class SpyNotificationSenderForShare implements NotificationSender
{
    /** @var list<array{userId: int, payload: array<string, mixed>}> */
    public array $calls = [];

    public function send(int $userId, array $payload): void
    {
        $this->calls[] = ['userId' => $userId, 'payload' => $payload];
    }
}

final class ShareEventTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function dispatcher(SpyNotificationSenderForShare $push, SpyNotificationSenderForShare $email): NotificationDispatcher
    {
        return new NotificationDispatcher(
            new InMemoryNotificationPreferenceRepositoryForShare(),
            new InMemoryNotificationLogRepositoryForShare(),
            $push,
            $email,
        );
    }

    public function test_GIVEN_two_friends_WHEN_sharing_an_event_THEN_a_friend_interest_notification_is_dispatched(): void
    {
        $friendship = new Friendship(id: 1, requesterId: 1, recipientId: 2, status: FriendshipStatus::Accepted);

        $friendships = Mockery::mock(FriendshipRepository::class);
        $friendships->shouldReceive('findBetween')->once()->with(1, 2)->andReturn($friendship);

        $push = new SpyNotificationSenderForShare();
        $email = new SpyNotificationSenderForShare();

        $useCase = new ShareEvent($friendships, $this->dispatcher($push, $email));

        $useCase->shareToFriend(1, 2, 10);

        $this->assertCount(1, $push->calls);
        $this->assertSame(2, $push->calls[0]['userId']);
    }

    public function test_GIVEN_two_users_who_are_not_friends_WHEN_sharing_an_event_THEN_it_throws_a_clear_error(): void
    {
        $friendships = Mockery::mock(FriendshipRepository::class);
        $friendships->shouldReceive('findBetween')->once()->with(1, 2)->andReturn(null);

        $push = new SpyNotificationSenderForShare();
        $email = new SpyNotificationSenderForShare();

        $useCase = new ShareEvent($friendships, $this->dispatcher($push, $email));

        $this->expectException(DomainException::class);

        $useCase->shareToFriend(1, 2, 10);

        $this->assertCount(0, $push->calls);
    }

    public function test_GIVEN_a_pending_not_yet_accepted_request_WHEN_sharing_an_event_THEN_it_throws_a_clear_error(): void
    {
        $friendship = new Friendship(id: 1, requesterId: 1, recipientId: 2, status: FriendshipStatus::Pending);

        $friendships = Mockery::mock(FriendshipRepository::class);
        $friendships->shouldReceive('findBetween')->once()->with(1, 2)->andReturn($friendship);

        $push = new SpyNotificationSenderForShare();
        $email = new SpyNotificationSenderForShare();

        $useCase = new ShareEvent($friendships, $this->dispatcher($push, $email));

        $this->expectException(DomainException::class);

        $useCase->shareToFriend(1, 2, 10);
    }
}
