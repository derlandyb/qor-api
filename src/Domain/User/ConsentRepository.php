<?php

namespace QOR\App\Domain\User;

use QOR\App\Domain\User\Enum\ConsentableType;
use QOR\App\Domain\User\Enum\ConsentType;

interface ConsentRepository
{
    /**
     * Records explicit, non-pre-checked acceptance (LGPD Art. 7). Each call
     * creates a new row — location consent is always separate from terms
     * consent, never bundled (ARCHITECTURE.md §7).
     */
    public function record(
        ConsentableType $consentableType,
        int $consentableId,
        ConsentType $consentType,
        string $policyVersion,
    ): ConsentRecord;

    /**
     * True if the most recent record of this type for this consentable has
     * not been revoked.
     */
    public function hasAccepted(ConsentableType $consentableType, int $consentableId, ConsentType $consentType): bool;

    /**
     * Marks the most recent active record of this type revoked, effective
     * immediately (AUTH-25).
     */
    public function revoke(ConsentableType $consentableType, int $consentableId, ConsentType $consentType): void;
}
