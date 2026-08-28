<?php

namespace Tests\Unit\Domain\Approval;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Approval\ApprovalDecision;
use QOR\App\Domain\Approval\Enum\ApprovalDecidableType;
use QOR\App\Domain\Approval\Enum\ApprovalOutcome;

class ApprovalDecisionTest extends TestCase
{
    public function test_GIVEN_an_approved_outcome_WHEN_constructing_THEN_no_reason_is_required(): void
    {
        $decision = new ApprovalDecision(
            id: 1,
            decidableType: ApprovalDecidableType::Venue,
            decidableId: 10,
            outcome: ApprovalOutcome::Approved,
            decidedBy: 5,
            decidedAt: new DateTimeImmutable(),
        );

        $this->assertNull($decision->reason);
    }

    public function test_GIVEN_a_rejected_outcome_without_a_reason_WHEN_constructing_THEN_it_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ApprovalDecision(
            id: 1,
            decidableType: ApprovalDecidableType::Venue,
            decidableId: 10,
            outcome: ApprovalOutcome::Rejected,
            decidedBy: 5,
            decidedAt: new DateTimeImmutable(),
        );
    }

    public function test_GIVEN_a_rejected_outcome_with_a_reason_WHEN_constructing_THEN_entity_is_valid(): void
    {
        $decision = new ApprovalDecision(
            id: 1,
            decidableType: ApprovalDecidableType::Promoter,
            decidableId: 10,
            outcome: ApprovalOutcome::Rejected,
            decidedBy: 5,
            decidedAt: new DateTimeImmutable(),
            reason: 'Documentação incompleta.',
        );

        $this->assertSame('Documentação incompleta.', $decision->reason);
    }
}
