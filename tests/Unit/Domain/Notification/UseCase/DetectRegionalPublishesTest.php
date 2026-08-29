<?php

namespace Tests\Unit\Domain\Notification\UseCase;

use DateTimeImmutable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\EventPage;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Notification\Enum\NotificationChannel;
use QOR\App\Domain\Notification\Enum\NotificationTriggerType;
use QOR\App\Domain\Notification\NotificationDispatcher;
use QOR\App\Domain\Notification\NotificationLog;
use QOR\App\Domain\Notification\NotificationLogRepository;
use QOR\App\Domain\Notification\NotificationPreference;
use QOR\App\Domain\Notification\NotificationPreferenceRepository;
use QOR\App\Domain\Notification\NotificationSender;
use QOR\App\Domain\Notification\UseCase\DetectRegionalPublishes;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\User\Enum\AddressSource;
use QOR\App\Domain\User\UserAddress;
use QOR\App\Domain\User\UserAddressRepository;

final class InMemoryUserAddressRepositoryForRegional implements UserAddressRepository
{
    /** @var list<UserAddress> */
    private array $addresses = [];

    public function add(UserAddress $address): void
    {
        $this->addresses[] = $address;
    }

    public function findByUserId(int $userId): ?UserAddress
    {
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

final class InMemoryNotificationPreferenceRepositoryForRegional implements NotificationPreferenceRepository
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

final class InMemoryNotificationLogRepositoryForRegional implements NotificationLogRepository
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

final class SpyNotificationSenderForRegional implements NotificationSender
{
    /** @var list<array{userId: int, payload: array<string, mixed>}> */
    public array $calls = [];

    public function send(int $userId, array $payload): void
    {
        $this->calls[] = ['userId' => $userId, 'payload' => $payload];
    }
}

final class DetectRegionalPublishesTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function event(int $id): Event
    {
        return new Event(
            id: $id,
            createdByType: EventCreatedByType::VenueAdmin,
            createdById: 1,
            title: "Show {$id}",
            description: 'Descrição',
            startsAt: new DateTimeImmutable('+1 week'),
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

    public function test_GIVEN_three_qualifying_events_in_one_window_WHEN_detecting_THEN_exactly_one_digest_dispatch_call_is_made(): void
    {
        $addresses = new InMemoryUserAddressRepositoryForRegional();
        $addresses->add($this->address(1, 10));

        $events = Mockery::mock(EventRepository::class);
        $events->shouldReceive('findUpcoming')->once()->with(City::Vitoria, null, null)
            ->andReturn(new EventPage(items: [$this->event(1), $this->event(2), $this->event(3)], nextCursor: null));

        $push = new SpyNotificationSenderForRegional();
        $email = new SpyNotificationSenderForRegional();
        $dispatcher = new NotificationDispatcher(
            new InMemoryNotificationPreferenceRepositoryForRegional(),
            new InMemoryNotificationLogRepositoryForRegional(),
            $push,
            $email,
        );

        $useCase = new DetectRegionalPublishes($addresses, $events, $dispatcher);
        $useCase->execute();

        $this->assertCount(1, $push->calls);
        $this->assertSame([1, 2, 3], $push->calls[0]['payload']['eventIds']);
    }

    public function test_GIVEN_a_fan_with_no_radius_set_WHEN_detecting_THEN_they_are_skipped(): void
    {
        $addresses = new InMemoryUserAddressRepositoryForRegional();
        $addresses->add($this->address(1, null));

        $events = Mockery::mock(EventRepository::class);
        $events->shouldNotReceive('findUpcoming');

        $push = new SpyNotificationSenderForRegional();
        $email = new SpyNotificationSenderForRegional();
        $dispatcher = new NotificationDispatcher(
            new InMemoryNotificationPreferenceRepositoryForRegional(),
            new InMemoryNotificationLogRepositoryForRegional(),
            $push,
            $email,
        );

        $useCase = new DetectRegionalPublishes($addresses, $events, $dispatcher);
        $useCase->execute();

        $this->assertCount(0, $push->calls);
    }

    public function test_GIVEN_no_qualifying_events_in_the_fans_city_WHEN_detecting_THEN_no_dispatch_call_is_made(): void
    {
        $addresses = new InMemoryUserAddressRepositoryForRegional();
        $addresses->add($this->address(1, 10));

        $events = Mockery::mock(EventRepository::class);
        $events->shouldReceive('findUpcoming')->once()->with(City::Vitoria, null, null)
            ->andReturn(new EventPage(items: [], nextCursor: null));

        $push = new SpyNotificationSenderForRegional();
        $email = new SpyNotificationSenderForRegional();
        $dispatcher = new NotificationDispatcher(
            new InMemoryNotificationPreferenceRepositoryForRegional(),
            new InMemoryNotificationLogRepositoryForRegional(),
            $push,
            $email,
        );

        $useCase = new DetectRegionalPublishes($addresses, $events, $dispatcher);
        $useCase->execute();

        $this->assertCount(0, $push->calls);
    }
}
