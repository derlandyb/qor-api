<?php

namespace Tests\Unit\Domain\Event;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\EventPage;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Shared\Enum\City;

/**
 * In-memory fake exercising the EventRepository contract itself, independent
 * of any Eloquent/Postgres concern (that's EloquentEventRepositoryTest).
 */
final class InMemoryEventRepository implements EventRepository
{
    /** @var array<int, Event> */
    private array $events = [];

    private int $nextId = 1;

    public function findUpcoming(?City $city, ?int $genreId, ?string $cursor): EventPage
    {
        return new EventPage(items: array_values($this->events), nextCursor: null);
    }

    public function findById(int $id): ?Event
    {
        return $this->events[$id] ?? null;
    }

    public function findByCreator(EventCreatedByType $createdByType, int $createdById): array
    {
        return array_values(array_filter(
            $this->events,
            fn (Event $event) => $event->createdByType === $createdByType && $event->createdById === $createdById,
        ));
    }

    public function save(Event $event): Event
    {
        $id = $event->id ?? $this->nextId++;

        $saved = new Event(
            id: $id,
            createdByType: $event->createdByType,
            createdById: $event->createdById,
            title: $event->title,
            description: $event->description,
            coverImageUrl: $event->coverImageUrl,
            startsAt: $event->startsAt,
            city: $event->city,
            genreId: $event->genreId,
            isFree: $event->isFree,
            status: $event->status,
            address: $event->address,
            ticketUrl: $event->ticketUrl,
            capacity: $event->capacity,
            ageRating: $event->ageRating,
            notes: $event->notes,
            rejectionFeedback: $event->rejectionFeedback,
        );

        $this->events[$id] = $saved;

        return $saved;
    }

    public function delete(int $id): void
    {
        unset($this->events[$id]);
    }
}

class EventRepositoryContractTest extends TestCase
{
    private function makeEvent(?int $id = null): Event
    {
        return new Event(
            id: $id,
            createdByType: EventCreatedByType::VenueAdmin,
            createdById: 1,
            title: 'Noite do Rock',
            description: 'Show ao vivo.',
            coverImageUrl: 'https://example.com/cover.jpg',
            startsAt: new DateTimeImmutable('+1 week'),
            city: City::Vitoria,
            genreId: 1,
            isFree: true,
        );
    }

    public function test_GIVEN_no_saved_event_WHEN_finding_by_id_THEN_it_returns_null(): void
    {
        $repository = new InMemoryEventRepository();

        $this->assertNull($repository->findById(999));
    }

    public function test_GIVEN_a_new_event_WHEN_saving_THEN_it_is_assigned_an_id_and_retrievable(): void
    {
        $repository = new InMemoryEventRepository();

        $saved = $repository->save($this->makeEvent());

        $this->assertNotNull($saved->id);
        $this->assertSame($saved->title, $repository->findById($saved->id)->title);
    }

    public function test_GIVEN_a_saved_event_WHEN_deleting_THEN_it_is_no_longer_findable(): void
    {
        $repository = new InMemoryEventRepository();
        $saved = $repository->save($this->makeEvent());

        $repository->delete($saved->id);

        $this->assertNull($repository->findById($saved->id));
    }
}
