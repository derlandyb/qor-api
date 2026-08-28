<?php

namespace QOR\App\Domain\Approval;

interface ApprovalDecisionRepository
{
    public function save(ApprovalDecision $decision): ApprovalDecision;

    /**
     * Venues and Promoters currently awaiting account approval (ADMIN-07–ADMIN-10).
     *
     * @return list<PendingAccount>
     */
    public function findPendingAccounts(): array;

    /**
     * IDs of Events currently in the publish-approval queue (ADMIN-16–ADMIN-19).
     *
     * @return list<int>
     */
    public function findPendingEvents(): array;
}
