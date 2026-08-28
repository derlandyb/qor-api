<?php

namespace QOR\App\Domain\Approval;

use DateTimeImmutable;
use InvalidArgumentException;
use QOR\App\Domain\Approval\Enum\ApprovalDecidableType;
use QOR\App\Domain\Approval\Enum\ApprovalOutcome;

final class ApprovalDecision
{
    public function __construct(
        public readonly ?int $id,
        public readonly ApprovalDecidableType $decidableType,
        public readonly int $decidableId,
        public readonly ApprovalOutcome $outcome,
        public readonly int $decidedBy,
        public readonly DateTimeImmutable $decidedAt,
        public readonly ?string $reason = null,
    ) {
        if (in_array($this->outcome, [ApprovalOutcome::Rejected, ApprovalOutcome::Suspended], true) && $this->reason === null) {
            throw new InvalidArgumentException('Uma justificativa é obrigatória para rejeição ou suspensão.');
        }
    }
}
