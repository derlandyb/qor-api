<?php

namespace Tests\Unit\Domain\Approval;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Approval\ApprovalDecision;
use QOR\App\Domain\Approval\ApprovalDecisionRepository;
use QOR\App\Domain\Approval\Enum\ApprovalDecidableType;
use QOR\App\Domain\Approval\Enum\ApprovalOutcome;

final class InMemoryApprovalDecisionRepository implements ApprovalDecisionRepository
{
    /** @var list<ApprovalDecision> */
    private array $decisions = [];

    private int $nextId = 1;

    public function save(ApprovalDecision $decision): ApprovalDecision
    {
        $saved = new ApprovalDecision(
            id: $decision->id ?? $this->nextId++,
            decidableType: $decision->decidableType,
            decidableId: $decision->decidableId,
            outcome: $decision->outcome,
            decidedBy: $decision->decidedBy,
            decidedAt: $decision->decidedAt,
            reason: $decision->reason,
        );

        $this->decisions[] = $saved;

        return $saved;
    }

    public function findPendingAccounts(): array
    {
        return [];
    }

    public function findPendingEvents(): array
    {
        return [];
    }

    /** @return list<ApprovalDecision> */
    public function all(): array
    {
        return $this->decisions;
    }
}

class ApprovalDecisionRepositoryContractTest extends TestCase
{
    public function test_GIVEN_a_decision_WHEN_saving_THEN_it_is_assigned_an_id(): void
    {
        $repository = new InMemoryApprovalDecisionRepository();

        $saved = $repository->save(new ApprovalDecision(
            id: null,
            decidableType: ApprovalDecidableType::Venue,
            decidableId: 1,
            outcome: ApprovalOutcome::Approved,
            decidedBy: 5,
            decidedAt: new DateTimeImmutable(),
        ));

        $this->assertNotNull($saved->id);
        $this->assertCount(1, $repository->all());
    }

    public function test_GIVEN_no_pending_items_WHEN_finding_pending_accounts_THEN_it_returns_an_empty_list(): void
    {
        $repository = new InMemoryApprovalDecisionRepository();

        $this->assertSame([], $repository->findPendingAccounts());
    }
}
