<?php

namespace Tests\Unit\Domain\Approval\UseCase;

use DateTimeImmutable;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Approval\ApprovalDecision;
use QOR\App\Domain\Approval\ApprovalDecisionRepository;
use QOR\App\Domain\Approval\Enum\ApprovalDecidableType;
use QOR\App\Domain\Approval\Enum\ApprovalOutcome;
use QOR\App\Domain\Approval\UseCase\DecideEventApproval;
use QOR\App\Domain\Event\DomainEvent\EventCancelled;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Shared\DomainEventPublisher;
use QOR\App\Domain\Shared\Enum\City;

class DecideEventApprovalTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function domainEvents(): DomainEventPublisher
    {
        $domainEvents = Mockery::mock(DomainEventPublisher::class);
        $domainEvents->shouldReceive('publish')->zeroOrMoreTimes();

        return $domainEvents;
    }

    private function makeEvent(DateTimeImmutable $startsAt, EventStatus $status = EventStatus::PendingReview): Event
    {
        return new Event(
            id: 1,
            createdByType: EventCreatedByType::VenueAdmin,
            createdById: 10,
            title: 'Show da Banda X',
            description: 'Um show incrível.',
            startsAt: $startsAt,
            city: City::Vitoria,
            genreId: 2,
            isFree: true,
            status: $status,
        );
    }

    public function test_GIVEN_event_starts_in_the_future_WHEN_approved_THEN_event_is_published(): void
    {
        $event = $this->makeEvent((new DateTimeImmutable())->modify('+1 day'));

        $eventRepository = Mockery::mock(EventRepository::class);
        $eventRepository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $eventRepository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Event $e) => $e->status === EventStatus::Published))
            ->andReturnUsing(fn (Event $e) => $e);

        $approvalDecisionRepository = Mockery::mock(ApprovalDecisionRepository::class);
        $approvalDecisionRepository->shouldReceive('save')
            ->once()
            ->andReturnUsing(fn (ApprovalDecision $d) => $d);

        $useCase = new DecideEventApproval($eventRepository, $approvalDecisionRepository, $this->domainEvents());

        $decision = $useCase->execute(1, ApprovalOutcome::Approved, null, 99);

        $this->assertSame(ApprovalOutcome::Approved, $decision->outcome);
        $this->assertSame(ApprovalDecidableType::Event, $decision->decidableType);
    }

    public function test_GIVEN_event_starts_in_the_past_WHEN_approved_THEN_event_is_directly_ended(): void
    {
        $event = $this->makeEvent((new DateTimeImmutable())->modify('-1 day'));

        $eventRepository = Mockery::mock(EventRepository::class);
        $eventRepository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $eventRepository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Event $e) => $e->status === EventStatus::Ended))
            ->andReturnUsing(fn (Event $e) => $e);

        $approvalDecisionRepository = Mockery::mock(ApprovalDecisionRepository::class);
        $approvalDecisionRepository->shouldReceive('save')
            ->once()
            ->andReturnUsing(fn (ApprovalDecision $d) => $d);

        $useCase = new DecideEventApproval($eventRepository, $approvalDecisionRepository, $this->domainEvents());

        $decision = $useCase->execute(1, ApprovalOutcome::Approved, null, 99);

        $this->assertSame(ApprovalOutcome::Approved, $decision->outcome);
    }

    public function test_GIVEN_feedback_WHEN_rejected_THEN_event_returns_to_draft_with_feedback(): void
    {
        $event = $this->makeEvent((new DateTimeImmutable())->modify('+1 day'));

        $eventRepository = Mockery::mock(EventRepository::class);
        $eventRepository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $eventRepository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(
                fn (Event $e) => $e->status === EventStatus::Draft
                    && $e->rejectionFeedback === 'Faltam informações sobre o local.'
            ))
            ->andReturnUsing(fn (Event $e) => $e);

        $approvalDecisionRepository = Mockery::mock(ApprovalDecisionRepository::class);
        $approvalDecisionRepository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (ApprovalDecision $d) => $d->reason === 'Faltam informações sobre o local.'))
            ->andReturnUsing(fn (ApprovalDecision $d) => $d);

        $useCase = new DecideEventApproval($eventRepository, $approvalDecisionRepository, $this->domainEvents());

        $decision = $useCase->execute(1, ApprovalOutcome::Rejected, 'Faltam informações sobre o local.', 99);

        $this->assertSame(ApprovalOutcome::Rejected, $decision->outcome);
    }

    public function test_GIVEN_no_feedback_WHEN_rejected_THEN_approval_decision_throws(): void
    {
        $event = $this->makeEvent((new DateTimeImmutable())->modify('+1 day'));

        $eventRepository = Mockery::mock(EventRepository::class);
        $eventRepository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $eventRepository->shouldReceive('save')->once()->andReturnUsing(fn (Event $e) => $e);

        $approvalDecisionRepository = Mockery::mock(ApprovalDecisionRepository::class);
        $approvalDecisionRepository->shouldNotReceive('save');

        $useCase = new DecideEventApproval($eventRepository, $approvalDecisionRepository, $this->domainEvents());

        $this->expectException(InvalidArgumentException::class);

        $useCase->execute(1, ApprovalOutcome::Rejected, null, 99);
    }

    public function test_GIVEN_a_pending_event_WHEN_force_cancelled_THEN_event_is_cancelled(): void
    {
        $event = $this->makeEvent((new DateTimeImmutable())->modify('+1 day'));

        $eventRepository = Mockery::mock(EventRepository::class);
        $eventRepository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $eventRepository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Event $e) => $e->status === EventStatus::Cancelled))
            ->andReturnUsing(fn (Event $e) => $e);

        $approvalDecisionRepository = Mockery::mock(ApprovalDecisionRepository::class);
        $approvalDecisionRepository->shouldReceive('save')
            ->once()
            ->andReturnUsing(fn (ApprovalDecision $d) => $d);

        $useCase = new DecideEventApproval($eventRepository, $approvalDecisionRepository, $this->domainEvents());

        $decision = $useCase->execute(1, ApprovalOutcome::ForceCancelled, null, 99);

        $this->assertSame(ApprovalOutcome::ForceCancelled, $decision->outcome);
    }

    public function test_GIVEN_a_pending_event_WHEN_force_cancelled_THEN_event_cancelled_fires_with_the_event_id(): void
    {
        $event = $this->makeEvent((new DateTimeImmutable())->modify('+1 day'));

        $eventRepository = Mockery::mock(EventRepository::class);
        $eventRepository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $eventRepository->shouldReceive('save')->once()->andReturnUsing(fn (Event $e) => $e);

        $approvalDecisionRepository = Mockery::mock(ApprovalDecisionRepository::class);
        $approvalDecisionRepository->shouldReceive('save')->once()->andReturnUsing(fn (ApprovalDecision $d) => $d);

        $domainEvents = Mockery::mock(DomainEventPublisher::class);
        $domainEvents->shouldReceive('publish')
            ->once()
            ->with(Mockery::on(fn (EventCancelled $e) => $e->eventId === 1));

        $useCase = new DecideEventApproval($eventRepository, $approvalDecisionRepository, $domainEvents);

        $useCase->execute(1, ApprovalOutcome::ForceCancelled, null, 99);
    }

    public function test_GIVEN_event_starts_in_the_future_WHEN_approved_THEN_event_cancelled_does_not_fire(): void
    {
        $event = $this->makeEvent((new DateTimeImmutable())->modify('+1 day'));

        $eventRepository = Mockery::mock(EventRepository::class);
        $eventRepository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $eventRepository->shouldReceive('save')->once()->andReturnUsing(fn (Event $e) => $e);

        $approvalDecisionRepository = Mockery::mock(ApprovalDecisionRepository::class);
        $approvalDecisionRepository->shouldReceive('save')->once()->andReturnUsing(fn (ApprovalDecision $d) => $d);

        $domainEvents = Mockery::mock(DomainEventPublisher::class);
        $domainEvents->shouldNotReceive('publish');

        $useCase = new DecideEventApproval($eventRepository, $approvalDecisionRepository, $domainEvents);

        $useCase->execute(1, ApprovalOutcome::Approved, null, 99);
    }

    public function test_GIVEN_any_outcome_WHEN_executed_THEN_decision_is_recorded_via_repository(): void
    {
        $event = $this->makeEvent((new DateTimeImmutable())->modify('+1 day'));

        $eventRepository = Mockery::mock(EventRepository::class);
        $eventRepository->shouldReceive('findById')->once()->with(1)->andReturn($event);
        $eventRepository->shouldReceive('save')->once()->andReturnUsing(fn (Event $e) => $e);

        $approvalDecisionRepository = Mockery::mock(ApprovalDecisionRepository::class);
        $approvalDecisionRepository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(
                fn (ApprovalDecision $d) => $d->decidableType === ApprovalDecidableType::Event
                    && $d->decidableId === 1
                    && $d->decidedBy === 99
            ))
            ->andReturnUsing(fn (ApprovalDecision $d) => $d);

        $useCase = new DecideEventApproval($eventRepository, $approvalDecisionRepository, $this->domainEvents());

        $useCase->execute(1, ApprovalOutcome::Approved, null, 99);
    }

    public function test_GIVEN_event_does_not_exist_WHEN_executed_THEN_throws(): void
    {
        $eventRepository = Mockery::mock(EventRepository::class);
        $eventRepository->shouldReceive('findById')->once()->with(404)->andReturn(null);
        $eventRepository->shouldNotReceive('save');

        $approvalDecisionRepository = Mockery::mock(ApprovalDecisionRepository::class);
        $approvalDecisionRepository->shouldNotReceive('save');

        $useCase = new DecideEventApproval($eventRepository, $approvalDecisionRepository, $this->domainEvents());

        $this->expectException(InvalidArgumentException::class);

        $useCase->execute(404, ApprovalOutcome::Approved, null, 99);
    }

    public function test_GIVEN_an_account_only_outcome_WHEN_executed_THEN_throws(): void
    {
        $eventRepository = Mockery::mock(EventRepository::class);
        $eventRepository->shouldNotReceive('findById');
        $eventRepository->shouldNotReceive('save');

        $approvalDecisionRepository = Mockery::mock(ApprovalDecisionRepository::class);
        $approvalDecisionRepository->shouldNotReceive('save');

        $useCase = new DecideEventApproval($eventRepository, $approvalDecisionRepository, $this->domainEvents());

        $this->expectException(InvalidArgumentException::class);

        $useCase->execute(1, ApprovalOutcome::Suspended, null, 99);
    }
}
