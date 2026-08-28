<?php

namespace QOR\App\Infrastructure\Persistence;

use QOR\App\Domain\Approval\ApprovalDecision;
use QOR\App\Domain\Approval\ApprovalDecisionRepository;
use QOR\App\Domain\Approval\Enum\ApprovalDecidableType;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Domain\Approval\PendingAccount;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Infrastructure\Persistence\Eloquent\ApprovalDecisionModel;
use QOR\App\Infrastructure\Persistence\Eloquent\EventModel;
use QOR\App\Infrastructure\Persistence\Eloquent\PromoterModel;
use QOR\App\Infrastructure\Persistence\Eloquent\VenueModel;

class EloquentApprovalDecisionRepository implements ApprovalDecisionRepository
{
    public function save(ApprovalDecision $decision): ApprovalDecision
    {
        $model = new ApprovalDecisionModel();

        $model->fill([
            'decidable_type' => $decision->decidableType->value,
            'decidable_id' => $decision->decidableId,
            'outcome' => $decision->outcome->value,
            'reason' => $decision->reason,
            'decided_by' => $decision->decidedBy,
            'decided_at' => $decision->decidedAt,
        ]);

        $model->save();

        return new ApprovalDecision(
            id: $model->id,
            decidableType: $model->decidable_type,
            decidableId: $model->decidable_id,
            outcome: $model->outcome,
            decidedBy: $model->decided_by,
            decidedAt: $model->decided_at->toDateTimeImmutable(),
            reason: $model->reason,
        );
    }

    public function findPendingAccounts(): array
    {
        $pending = [];

        /** @var list<int> $venueIds */
        $venueIds = VenueModel::where('approval_status', ApprovalStatus::PendingApproval->value)->pluck('id')->all();
        foreach ($venueIds as $id) {
            $pending[] = new PendingAccount(ApprovalDecidableType::Venue, $id);
        }

        /** @var list<int> $promoterIds */
        $promoterIds = PromoterModel::where('approval_status', ApprovalStatus::PendingApproval->value)->pluck('id')->all();
        foreach ($promoterIds as $id) {
            $pending[] = new PendingAccount(ApprovalDecidableType::Promoter, $id);
        }

        return $pending;
    }

    public function findPendingEvents(): array
    {
        /** @var list<int> $ids */
        $ids = EventModel::where('status', EventStatus::PendingReview->value)->pluck('id')->all();

        return $ids;
    }
}
