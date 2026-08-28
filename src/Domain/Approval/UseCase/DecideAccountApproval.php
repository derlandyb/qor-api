<?php

namespace QOR\App\Domain\Approval\UseCase;

use DateTimeImmutable;
use InvalidArgumentException;
use QOR\App\Domain\Approval\ApprovalDecision;
use QOR\App\Domain\Approval\ApprovalDecisionRepository;
use QOR\App\Domain\Approval\Enum\ApprovalDecidableType;
use QOR\App\Domain\Approval\Enum\ApprovalOutcome;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Domain\Promoter\Promoter;
use QOR\App\Domain\Promoter\PromoterRepository;
use QOR\App\Domain\Venue\Venue;
use QOR\App\Domain\Venue\VenueRepository;

final class DecideAccountApproval
{
    public function __construct(
        private readonly VenueRepository $venueRepository,
        private readonly PromoterRepository $promoterRepository,
        private readonly ApprovalDecisionRepository $approvalDecisionRepository,
    ) {
    }

    public function execute(
        ApprovalDecidableType $accountType,
        int $accountId,
        ApprovalOutcome $outcome,
        ?string $reason,
        int $decidedBy,
    ): ApprovalDecision {
        if ($accountType === ApprovalDecidableType::Event) {
            throw new InvalidArgumentException('Este caso de uso não trata aprovação de eventos.');
        }

        $newStatus = match ($outcome) {
            ApprovalOutcome::Approved => ApprovalStatus::Approved,
            ApprovalOutcome::Rejected => ApprovalStatus::Rejected,
            ApprovalOutcome::Suspended => ApprovalStatus::Suspended,
            ApprovalOutcome::SuspensionLifted => ApprovalStatus::Approved,
            ApprovalOutcome::ForceCancelled => throw new InvalidArgumentException('Este caso de uso não trata cancelamento forçado de eventos.'),
        };

        if ($accountType === ApprovalDecidableType::Venue) {
            $venue = $this->venueRepository->findById($accountId);

            if ($venue === null) {
                throw new InvalidArgumentException('Conta não encontrada.');
            }

            $this->venueRepository->save(new Venue(
                id: $venue->id,
                venueAdminUserId: $venue->venueAdminUserId,
                name: $venue->name,
                description: $venue->description,
                address: $venue->address,
                city: $venue->city,
                contactPhone: $venue->contactPhone,
                contactEmail: $venue->contactEmail,
                approvalStatus: $newStatus,
                imageUrl: $venue->imageUrl,
            ));
        } else {
            $promoter = $this->promoterRepository->findById($accountId);

            if ($promoter === null) {
                throw new InvalidArgumentException('Conta não encontrada.');
            }

            $this->promoterRepository->save(new Promoter(
                id: $promoter->id,
                userId: $promoter->userId,
                name: $promoter->name,
                contactPhone: $promoter->contactPhone,
                contactEmail: $promoter->contactEmail,
                approvalStatus: $newStatus,
                instagram: $promoter->instagram,
                tiktok: $promoter->tiktok,
            ));
        }

        return $this->approvalDecisionRepository->save(new ApprovalDecision(
            id: null,
            decidableType: $accountType,
            decidableId: $accountId,
            outcome: $outcome,
            decidedBy: $decidedBy,
            decidedAt: new DateTimeImmutable(),
            reason: $reason,
        ));
    }
}
