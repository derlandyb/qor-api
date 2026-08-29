<?php

namespace Tests\Unit\Domain\Notification\UseCase;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Notification\Enum\NotificationChannel;
use QOR\App\Domain\Notification\Enum\NotificationTriggerType;
use QOR\App\Domain\Notification\NotificationDispatcher;
use QOR\App\Domain\Notification\NotificationLog;
use QOR\App\Domain\Notification\NotificationLogRepository;
use QOR\App\Domain\Notification\NotificationPreference;
use QOR\App\Domain\Notification\NotificationPreferenceRepository;
use QOR\App\Domain\Notification\NotificationSender;
use QOR\App\Domain\Notification\UseCase\DetectNearbyReminders;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\Social\FavoritePage;
use QOR\App\Domain\Social\FavoriteRepository;
use QOR\App\Domain\User\Enum\AddressSource;
use QOR\App\Domain\User\UserAddress;
use QOR\App\Domain\User\UserAddressRepository;

final class InMemoryUserAddressRepositoryForNearby implements UserAddressRepository
{
    /** @var list<UserAddress> */
    private array $addresses = [];

    public function add(UserAddress $address): void
    {
        $this->addresses[] = $address;
    }

    public function findByUserId(int $userId): ?UserAddress
    {
        foreach ($this->addresses as $address) {
            if ($address->userId === $userId) {
                return $address;
            }
        }

        return null;
    }

    public function save(UserAddress $address): UserAddress
    {
        return $address;
    }

    public function listAllWithAddress(): array
    {
        return $this->addresses;
    }
}

final class FakeFavoriteRepositoryForNearby implements FavoriteRepository
{
    /** @var array<int, list<Event>> */
    private array $favoritesByUser = [];

    public function setFavorites(int $userId, array $events): void
    {
        $this->favoritesByUser[$userId] = $events;
    }

    public function toggle(int $userId, int $eventId): \QOR\App\Domain\Social\Enum\FavoriteState
    {
        return \QOR\App\Domain\Social\Enum\FavoriteState::Favorited;
    }

    public function listForUser(int $userId, ?string $cursor): FavoritePage
    {
        return new FavoritePage(items: $this->favoritesByUser[$userId] ?? [], nextCursor: null);
    }

    public function hasUserFavorited(int $userId, int $eventId): bool
    {
        foreach ($this->favoritesByUser[$userId] ?? [] as $event) {
            if ($event->id === $eventId) {
                return true;
            }
        }

        return false;
    }
}

final class InMemoryNotificationPreferenceRepositoryForNearby implements NotificationPreferenceRepository
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

final class InMemoryNotificationLogRepositoryForNearby implements NotificationLogRepository
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

    public function hasRecentSendForEvent(int $userId, int $eventId, int $windowMinutes): bool
    {
        foreach ($this->sent as $entry) {
            if ($entry['userId'] === $userId && $entry['eventId'] === $eventId) {
                return true;
            }
        }

        return false;
    }

    public function record(int $userId, NotificationTriggerType $triggerType, NotificationChannel $channel, ?int $eventId = null): NotificationLog
    {
        $this->sent[] = ['userId' => $userId, 'triggerType' => $triggerType, 'eventId' => $eventId];

        return new NotificationLog(id: null, userId: $userId, triggerType: $triggerType, channel: $channel, eventId: $eventId);
    }
}

final class SpyNotificationSenderForNearby implements NotificationSender
{
    /** @var list<array{userId: int, payload: array<string, mixed>}> */
    public array $calls = [];

    public function send(int $userId, array $payload): void
    {
        $this->calls[] = ['userId' => $userId, 'payload' => $payload];
    }
}

final class DetectNearbyRemindersTest extends TestCase
{
    private function event(int $id, DateTimeImmutable $startsAt): Event
    {
        return new Event(
            id: $id,
            createdByType: EventCreatedByType::VenueAdmin,
            createdById: 1,
            title: "Show {$id}",
            description: 'Descrição',
            startsAt: $startsAt,
            city: City::Vitoria,
            genreId: 1,
            isFree: true,
        );
    }

    private function address(int $userId, ?int $radiusKm): UserAddress
    {
        return new UserAddress(
            id: $userId,
            userId: $userId,
            city: City::Vitoria,
            state: 'ES',
            source: AddressSource::Manual,
            radiusKm: $radiusKm,
        );
    }

    public function test_GIVEN_a_fan_without_a_radius_set_WHEN_detecting_THEN_they_are_skipped(): void
    {
        $addresses = new InMemoryUserAddressRepositoryForNearby();
        $addresses->add($this->address(1, null));

        $favorites = new FakeFavoriteRepositoryForNearby();
        $favorites->setFavorites(1, [$this->event(10, new DateTimeImmutable('+2 hours'))]);

        $push = new SpyNotificationSenderForNearby();
        $email = new SpyNotificationSenderForNearby();
        $dispatcher = new NotificationDispatcher(
            new InMemoryNotificationPreferenceRepositoryForNearby(),
            new InMemoryNotificationLogRepositoryForNearby(),
            $push,
            $email,
        );

        $useCase = new DetectNearbyReminders($addresses, $favorites, $dispatcher, leadHours: 24);
        $useCase->execute();

        $this->assertCount(0, $push->calls);
    }

    public function test_GIVEN_a_favorited_event_starting_within_the_lead_window_WHEN_detecting_THEN_a_reminder_is_dispatched(): void
    {
        $addresses = new InMemoryUserAddressRepositoryForNearby();
        $addresses->add($this->address(1, 10));

        $favorites = new FakeFavoriteRepositoryForNearby();
        $favorites->setFavorites(1, [$this->event(10, new DateTimeImmutable('+2 hours'))]);

        $push = new SpyNotificationSenderForNearby();
        $email = new SpyNotificationSenderForNearby();
        $dispatcher = new NotificationDispatcher(
            new InMemoryNotificationPreferenceRepositoryForNearby(),
            new InMemoryNotificationLogRepositoryForNearby(),
            $push,
            $email,
        );

        $useCase = new DetectNearbyReminders($addresses, $favorites, $dispatcher, leadHours: 24);
        $useCase->execute();

        $this->assertCount(1, $push->calls);
        $this->assertSame(1, $push->calls[0]['userId']);
    }

    public function test_GIVEN_a_reminder_already_sent_for_the_same_fan_and_event_WHEN_detecting_again_THEN_it_is_not_resent(): void
    {
        $addresses = new InMemoryUserAddressRepositoryForNearby();
        $addresses->add($this->address(1, 10));

        $favorites = new FakeFavoriteRepositoryForNearby();
        $favorites->setFavorites(1, [$this->event(10, new DateTimeImmutable('+2 hours'))]);

        $push = new SpyNotificationSenderForNearby();
        $email = new SpyNotificationSenderForNearby();
        $dispatcher = new NotificationDispatcher(
            new InMemoryNotificationPreferenceRepositoryForNearby(),
            new InMemoryNotificationLogRepositoryForNearby(),
            $push,
            $email,
        );

        $useCase = new DetectNearbyReminders($addresses, $favorites, $dispatcher, leadHours: 24);
        $useCase->execute();
        $useCase->execute();

        $this->assertCount(1, $push->calls);
    }

    public function test_GIVEN_global_silence_enabled_WHEN_detecting_THEN_no_notification_is_sent(): void
    {
        $addresses = new InMemoryUserAddressRepositoryForNearby();
        $addresses->add($this->address(1, 10));

        $favorites = new FakeFavoriteRepositoryForNearby();
        $favorites->setFavorites(1, [$this->event(10, new DateTimeImmutable('+2 hours'))]);

        $preferences = new InMemoryNotificationPreferenceRepositoryForNearby();
        $preferences->save(new NotificationPreference(id: null, userId: 1, silenceAll: true));

        $push = new SpyNotificationSenderForNearby();
        $email = new SpyNotificationSenderForNearby();
        $dispatcher = new NotificationDispatcher(
            $preferences,
            new InMemoryNotificationLogRepositoryForNearby(),
            $push,
            $email,
        );

        $useCase = new DetectNearbyReminders($addresses, $favorites, $dispatcher, leadHours: 24);
        $useCase->execute();

        $this->assertCount(0, $push->calls);
    }

    public function test_GIVEN_a_favorited_event_starting_beyond_the_lead_window_WHEN_detecting_THEN_no_reminder_is_dispatched(): void
    {
        $addresses = new InMemoryUserAddressRepositoryForNearby();
        $addresses->add($this->address(1, 10));

        $favorites = new FakeFavoriteRepositoryForNearby();
        $favorites->setFavorites(1, [$this->event(10, new DateTimeImmutable('+2 days'))]);

        $push = new SpyNotificationSenderForNearby();
        $email = new SpyNotificationSenderForNearby();
        $dispatcher = new NotificationDispatcher(
            new InMemoryNotificationPreferenceRepositoryForNearby(),
            new InMemoryNotificationLogRepositoryForNearby(),
            $push,
            $email,
        );

        $useCase = new DetectNearbyReminders($addresses, $favorites, $dispatcher, leadHours: 24);
        $useCase->execute();

        $this->assertCount(0, $push->calls);
    }
}
