<?php

namespace Tests\Unit\Domain\Event\UseCase;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Domain\Event\DomainEvent\EventCancelled;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Event\UseCase\CancelEvent;
use QOR\App\Domain\Shared\DomainEventPublisher;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\Venue\Venue;

class CancelEventTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function domainEvents(): DomainEventPublisher
    {
        $domainEvents = Mockery::mock(DomainEventPublisher::class);
        $domainEvents->shouldReceive('publish')->zeroOrMoreTimes();

        return $domainEvents;
    }

    private function approvedVenue(int $id = 1): Venue
    {
        return new Venue(
            id: $id,
            venueAdminUserId: 10,
            name: 'Casa de Show',
            description: 'Um lugar legal',
            address: 'Rua das Flores, 123',
            city: City::Vitoria,
            contactPhone: '27999999999',
            contactEmail: 'contato@casa.com',
            approvalStatus: ApprovalStatus::Approved,
        );
    }

    private function makeEvent(EventStatus $status, int $createdById = 1): Event
    {
        return new Event(
            id: 1,
            createdByType: EventCreatedByType::VenueAdmin,
            createdById: $createdById,
            title: 'Show da Banda X',
            description: 'Descrição original.',
            startsAt: new DateTimeImmutable('+1 week'),
            city: City::Vitoria,
            genreId: 2,
            isFree: true,
            status: $status,
        );
    }

    public function test_GIVEN_a_published_event_owned_by_the_organizer_WHEN_cancelling_THEN_it_transitions_to_cancelled(): void
    {
        $venue = $this->approvedVenue();
        $event = $this->makeEvent(EventStatus::Published, createdById: 1);

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $repository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Event $e) => $e->status === EventStatus::Cancelled))
            ->andReturnUsing(fn (Event $e) => $e);

        $useCase = new CancelEvent($repository, $this->domainEvents());

        $result = $useCase->execute(eventId: 1, organizer: $venue);

        $this->assertSame(EventStatus::Cancelled, $result->status);
    }

    public function test_GIVEN_a_published_event_owned_by_the_organizer_WHEN_cancelling_THEN_event_cancelled_fires_with_the_event_id(): void
    {
        $venue = $this->approvedVenue();
        $event = $this->makeEvent(EventStatus::Published, createdById: 1);

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $repository->shouldReceive('save')->once()->andReturnUsing(fn (Event $e) => $e);

        $domainEvents = Mockery::mock(DomainEventPublisher::class);
        $domainEvents->shouldReceive('publish')
            ->once()
            ->with(Mockery::on(fn (EventCancelled $e) => $e->eventId === 1));

        $useCase = new CancelEvent($repository, $domainEvents);

        $useCase->execute(eventId: 1, organizer: $venue);
    }

    public function test_GIVEN_a_draft_event_owned_by_the_organizer_WHEN_cancelling_THEN_it_throws(): void
    {
        $venue = $this->approvedVenue();
        $event = $this->makeEvent(EventStatus::Draft, createdById: 1);

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $repository->shouldNotReceive('save');

        $useCase = new CancelEvent($repository, $this->domainEvents());

        $this->expectException(DomainException::class);

        $useCase->execute(eventId: 1, organizer: $venue);
    }

    public function test_GIVEN_an_event_not_owned_by_the_organizer_WHEN_cancelling_THEN_it_throws(): void
    {
        $venue = $this->approvedVenue(id: 1);
        $event = $this->makeEvent(EventStatus::Published, createdById: 999);

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $repository->shouldNotReceive('save');

        $useCase = new CancelEvent($repository, $this->domainEvents());

        $this->expectException(InvalidArgumentException::class);

        $useCase->execute(eventId: 1, organizer: $venue);
    }

    public function test_GIVEN_the_event_does_not_exist_WHEN_cancelling_THEN_it_throws(): void
    {
        $venue = $this->approvedVenue();

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(404)->andReturn(null);
        $repository->shouldNotReceive('save');

        $useCase = new CancelEvent($repository, $this->domainEvents());

        $this->expectException(InvalidArgumentException::class);

        $useCase->execute(eventId: 404, organizer: $venue);
    }
}
