<?php

namespace Tests\Unit\Domain\Approval\UseCase;

use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Approval\ApprovalDecision;
use QOR\App\Domain\Approval\ApprovalDecisionRepository;
use QOR\App\Domain\Approval\Enum\ApprovalDecidableType;
use QOR\App\Domain\Approval\Enum\ApprovalOutcome;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Domain\Approval\UseCase\DecideAccountApproval;
use QOR\App\Domain\Promoter\Promoter;
use QOR\App\Domain\Promoter\PromoterRepository;
use QOR\App\Domain\Venue\Venue;
use QOR\App\Domain\Venue\VenueRepository;
use QOR\App\Domain\Shared\Enum\City;

class DecideAccountApprovalTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function makeVenue(ApprovalStatus $status = ApprovalStatus::PendingApproval): Venue
    {
        return new Venue(
            id: 1,
            venueAdminUserId: 10,
            name: 'Casa de Show',
            description: 'Um lugar legal',
            address: 'Rua A, 123',
            city: City::Vitoria,
            contactPhone: '27999999999',
            contactEmail: 'venue@example.com',
            approvalStatus: $status,
        );
    }

    private function makePromoter(ApprovalStatus $status = ApprovalStatus::PendingApproval): Promoter
    {
        return new Promoter(
            id: 2,
            userId: 20,
            name: 'Produtora XYZ',
            contactPhone: '27988888888',
            contactEmail: 'promoter@example.com',
            approvalStatus: $status,
        );
    }

    public function test_GIVEN_a_pending_venue_WHEN_approving_THEN_status_becomes_approved_and_decision_is_saved(): void
    {
        $venue = $this->makeVenue();

        $venueRepository = Mockery::mock(VenueRepository::class);
        $venueRepository->shouldReceive('findById')->once()->with(1)->andReturn($venue);
        $venueRepository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Venue $v) => $v->approvalStatus === ApprovalStatus::Approved))
            ->andReturnUsing(fn (Venue $v) => $v);

        $promoterRepository = Mockery::mock(PromoterRepository::class);

        $decisionRepository = Mockery::mock(ApprovalDecisionRepository::class);
        $decisionRepository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (ApprovalDecision $d) => $d->outcome === ApprovalOutcome::Approved
                && $d->decidableType === ApprovalDecidableType::Venue
                && $d->decidableId === 1))
            ->andReturnUsing(fn (ApprovalDecision $d) => new ApprovalDecision(
                id: 100,
                decidableType: $d->decidableType,
                decidableId: $d->decidableId,
                outcome: $d->outcome,
                decidedBy: $d->decidedBy,
                decidedAt: $d->decidedAt,
                reason: $d->reason,
            ));

        $useCase = new DecideAccountApproval($venueRepository, $promoterRepository, $decisionRepository);

        $result = $useCase->execute(
            accountType: ApprovalDecidableType::Venue,
            accountId: 1,
            outcome: ApprovalOutcome::Approved,
            reason: null,
            decidedBy: 99,
        );

        $this->assertSame(100, $result->id);
        $this->assertSame(ApprovalOutcome::Approved, $result->outcome);
    }

    public function test_GIVEN_a_reason_WHEN_rejecting_a_venue_THEN_status_becomes_rejected_and_decision_is_saved(): void
    {
        $venue = $this->makeVenue();

        $venueRepository = Mockery::mock(VenueRepository::class);
        $venueRepository->shouldReceive('findById')->once()->with(1)->andReturn($venue);
        $venueRepository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Venue $v) => $v->approvalStatus === ApprovalStatus::Rejected))
            ->andReturnUsing(fn (Venue $v) => $v);

        $promoterRepository = Mockery::mock(PromoterRepository::class);

        $decisionRepository = Mockery::mock(ApprovalDecisionRepository::class);
        $decisionRepository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (ApprovalDecision $d) => $d->outcome === ApprovalOutcome::Rejected
                && $d->reason === 'Documentação inválida'))
            ->andReturnUsing(fn (ApprovalDecision $d) => $d);

        $useCase = new DecideAccountApproval($venueRepository, $promoterRepository, $decisionRepository);

        $result = $useCase->execute(
            accountType: ApprovalDecidableType::Venue,
            accountId: 1,
            outcome: ApprovalOutcome::Rejected,
            reason: 'Documentação inválida',
            decidedBy: 99,
        );

        $this->assertSame(ApprovalOutcome::Rejected, $result->outcome);
    }

    public function test_GIVEN_no_reason_WHEN_rejecting_THEN_approval_decisions_own_exception_propagates(): void
    {
        $venue = $this->makeVenue();

        $venueRepository = Mockery::mock(VenueRepository::class);
        $venueRepository->shouldReceive('findById')->once()->with(1)->andReturn($venue);
        $venueRepository->shouldReceive('save')->once()->andReturnUsing(fn (Venue $v) => $v);

        $promoterRepository = Mockery::mock(PromoterRepository::class);
        $decisionRepository = Mockery::mock(ApprovalDecisionRepository::class);

        $useCase = new DecideAccountApproval($venueRepository, $promoterRepository, $decisionRepository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Uma justificativa é obrigatória para rejeição ou suspensão.');

        $useCase->execute(
            accountType: ApprovalDecidableType::Venue,
            accountId: 1,
            outcome: ApprovalOutcome::Rejected,
            reason: null,
            decidedBy: 99,
        );
    }

    public function test_GIVEN_an_approved_venue_WHEN_suspending_THEN_status_becomes_suspended(): void
    {
        $venue = $this->makeVenue(ApprovalStatus::Approved);

        $venueRepository = Mockery::mock(VenueRepository::class);
        $venueRepository->shouldReceive('findById')->once()->with(1)->andReturn($venue);
        $venueRepository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Venue $v) => $v->approvalStatus === ApprovalStatus::Suspended))
            ->andReturnUsing(fn (Venue $v) => $v);

        $promoterRepository = Mockery::mock(PromoterRepository::class);

        $decisionRepository = Mockery::mock(ApprovalDecisionRepository::class);
        $decisionRepository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (ApprovalDecision $d) => $d->outcome === ApprovalOutcome::Suspended))
            ->andReturnUsing(fn (ApprovalDecision $d) => $d);

        $useCase = new DecideAccountApproval($venueRepository, $promoterRepository, $decisionRepository);

        $result = $useCase->execute(
            accountType: ApprovalDecidableType::Venue,
            accountId: 1,
            outcome: ApprovalOutcome::Suspended,
            reason: 'Reclamações de usuários',
            decidedBy: 99,
        );

        $this->assertSame(ApprovalOutcome::Suspended, $result->outcome);
    }

    public function test_GIVEN_a_suspended_promoter_WHEN_lifting_suspension_THEN_status_becomes_approved(): void
    {
        $promoter = $this->makePromoter(ApprovalStatus::Suspended);

        $venueRepository = Mockery::mock(VenueRepository::class);

        $promoterRepository = Mockery::mock(PromoterRepository::class);
        $promoterRepository->shouldReceive('findById')->once()->with(2)->andReturn($promoter);
        $promoterRepository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Promoter $p) => $p->approvalStatus === ApprovalStatus::Approved))
            ->andReturnUsing(fn (Promoter $p) => $p);

        $decisionRepository = Mockery::mock(ApprovalDecisionRepository::class);
        $decisionRepository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (ApprovalDecision $d) => $d->outcome === ApprovalOutcome::SuspensionLifted
                && $d->decidableType === ApprovalDecidableType::Promoter
                && $d->decidableId === 2))
            ->andReturnUsing(fn (ApprovalDecision $d) => $d);

        $useCase = new DecideAccountApproval($venueRepository, $promoterRepository, $decisionRepository);

        $result = $useCase->execute(
            accountType: ApprovalDecidableType::Promoter,
            accountId: 2,
            outcome: ApprovalOutcome::SuspensionLifted,
            reason: null,
            decidedBy: 99,
        );

        $this->assertSame(ApprovalOutcome::SuspensionLifted, $result->outcome);
    }

    public function test_GIVEN_account_not_found_WHEN_executing_THEN_it_throws_invalid_argument_exception(): void
    {
        $venueRepository = Mockery::mock(VenueRepository::class);
        $venueRepository->shouldReceive('findById')->once()->with(1)->andReturn(null);

        $promoterRepository = Mockery::mock(PromoterRepository::class);
        $decisionRepository = Mockery::mock(ApprovalDecisionRepository::class);

        $useCase = new DecideAccountApproval($venueRepository, $promoterRepository, $decisionRepository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Conta não encontrada.');

        $useCase->execute(
            accountType: ApprovalDecidableType::Venue,
            accountId: 1,
            outcome: ApprovalOutcome::Approved,
            reason: null,
            decidedBy: 99,
        );
    }

    public function test_GIVEN_event_account_type_WHEN_executing_THEN_it_throws_invalid_argument_exception(): void
    {
        $venueRepository = Mockery::mock(VenueRepository::class);
        $promoterRepository = Mockery::mock(PromoterRepository::class);
        $decisionRepository = Mockery::mock(ApprovalDecisionRepository::class);

        $useCase = new DecideAccountApproval($venueRepository, $promoterRepository, $decisionRepository);

        $this->expectException(InvalidArgumentException::class);

        $useCase->execute(
            accountType: ApprovalDecidableType::Event,
            accountId: 1,
            outcome: ApprovalOutcome::Approved,
            reason: null,
            decidedBy: 99,
        );
    }

    public function test_GIVEN_force_cancelled_outcome_WHEN_executing_THEN_it_throws_invalid_argument_exception(): void
    {
        $venueRepository = Mockery::mock(VenueRepository::class);
        $promoterRepository = Mockery::mock(PromoterRepository::class);
        $decisionRepository = Mockery::mock(ApprovalDecisionRepository::class);

        $useCase = new DecideAccountApproval($venueRepository, $promoterRepository, $decisionRepository);

        $this->expectException(InvalidArgumentException::class);

        $useCase->execute(
            accountType: ApprovalDecidableType::Venue,
            accountId: 1,
            outcome: ApprovalOutcome::ForceCancelled,
            reason: null,
            decidedBy: 99,
        );
    }
}
