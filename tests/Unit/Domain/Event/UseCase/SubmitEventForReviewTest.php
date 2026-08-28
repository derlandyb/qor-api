<?php

namespace Tests\Unit\Domain\Event\UseCase;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Event\UseCase\SubmitEventForReview;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\Venue\Venue;

class SubmitEventForReviewTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function approvedVenue(): Venue
    {
        return new Venue(
            id: 1,
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

    private function draftEvent(EventStatus $status = EventStatus::Draft): Event
    {
        return new Event(
            id: 5,
            createdByType: EventCreatedByType::VenueAdmin,
            createdById: 1,
            title: 'Noite Eletrônica',
            description: 'Uma noite incrível',
            startsAt: new DateTimeImmutable('+1 week'),
            city: City::Vitoria,
            genreId: 1,
            isFree: true,
            status: $status,
            address: 'Rua das Flores, 123',
        );
    }

    public function test_GIVEN_a_draft_event_and_approved_organizer_WHEN_submitting_THEN_it_transitions_to_pending_review(): void
    {
        $venue = $this->approvedVenue();
        $event = $this->draftEvent();

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(5)->andReturn($event);
        $repository->shouldReceive('save')
            ->once()
            ->withArgs(fn (Event $e) => $e->status === EventStatus::PendingReview)
            ->andReturnUsing(fn (Event $e) => $e);

        $useCase = new SubmitEventForReview($repository);

        $result = $useCase->execute(5, $venue);

        $this->assertSame(EventStatus::PendingReview, $result->status);
    }

    public function test_GIVEN_an_unapproved_organizer_WHEN_submitting_THEN_it_is_blocked(): void
    {
        $venue = new Venue(
            id: 1,
            venueAdminUserId: 10,
            name: 'Casa de Show',
            description: 'Um lugar legal',
            address: 'Rua das Flores, 123',
            city: City::Vitoria,
            contactPhone: '27999999999',
            contactEmail: 'contato@casa.com',
            approvalStatus: ApprovalStatus::Suspended,
        );
        $event = $this->draftEvent();

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(5)->andReturn($event);
        $repository->shouldNotReceive('save');

        $useCase = new SubmitEventForReview($repository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sua conta ainda não foi aprovada.');

        $useCase->execute(5, $venue);
    }

    public function test_GIVEN_an_event_that_does_not_exist_WHEN_submitting_THEN_it_throws(): void
    {
        $venue = $this->approvedVenue();

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(999)->andReturn(null);
        $repository->shouldNotReceive('save');

        $useCase = new SubmitEventForReview($repository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Evento não encontrado.');

        $useCase->execute(999, $venue);
    }

    public function test_GIVEN_an_event_that_cannot_legally_transition_WHEN_submitting_THEN_the_domain_exception_propagates(): void
    {
        $venue = $this->approvedVenue();
        $event = $this->draftEvent(EventStatus::Published);

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(5)->andReturn($event);
        $repository->shouldNotReceive('save');

        $useCase = new SubmitEventForReview($repository);

        $this->expectException(DomainException::class);

        $useCase->execute(5, $venue);
    }
}
