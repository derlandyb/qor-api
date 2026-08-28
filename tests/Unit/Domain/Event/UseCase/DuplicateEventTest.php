<?php

namespace Tests\Unit\Domain\Event\UseCase;

use DateTimeImmutable;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Event\UseCase\DuplicateEvent;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\Venue\Venue;

class DuplicateEventTest extends TestCase
{
    use MockeryPHPUnitIntegration;

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
            rejectionFeedback: 'Feedback anterior.',
        );
    }

    public function test_GIVEN_an_event_owned_by_the_organizer_WHEN_duplicating_THEN_a_new_draft_is_built_from_the_source(): void
    {
        $venue = $this->approvedVenue();
        $event = $this->makeEvent(EventStatus::Published, createdById: 1);
        $newStartsAt = new DateTimeImmutable('+1 month');

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $repository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(
                fn (Event $e) => $e->id === null
                    && $e->status === EventStatus::Draft
                    && $e->title === $event->title
                    && $e->startsAt == $newStartsAt
                    && $e->rejectionFeedback === null
            ))
            ->andReturnUsing(fn (Event $e) => new Event(
                id: 2,
                createdByType: $e->createdByType,
                createdById: $e->createdById,
                title: $e->title,
                description: $e->description,
                startsAt: $e->startsAt,
                city: $e->city,
                genreId: $e->genreId,
                isFree: $e->isFree,
                status: $e->status,
            ));

        $useCase = new DuplicateEvent($repository);

        $result = $useCase->execute(eventId: 1, organizer: $venue, startsAt: $newStartsAt);

        $this->assertSame(2, $result->id);
        $this->assertSame(EventStatus::Draft, $result->status);
    }

    public function test_GIVEN_an_event_not_owned_by_the_organizer_WHEN_duplicating_THEN_it_throws(): void
    {
        $venue = $this->approvedVenue(id: 1);
        $event = $this->makeEvent(EventStatus::Published, createdById: 999);

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $repository->shouldNotReceive('save');

        $useCase = new DuplicateEvent($repository);

        $this->expectException(InvalidArgumentException::class);

        $useCase->execute(eventId: 1, organizer: $venue, startsAt: new DateTimeImmutable('+1 month'));
    }

    public function test_GIVEN_the_event_does_not_exist_WHEN_duplicating_THEN_it_throws(): void
    {
        $venue = $this->approvedVenue();

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(404)->andReturn(null);
        $repository->shouldNotReceive('save');

        $useCase = new DuplicateEvent($repository);

        $this->expectException(InvalidArgumentException::class);

        $useCase->execute(eventId: 404, organizer: $venue, startsAt: new DateTimeImmutable('+1 month'));
    }
}
