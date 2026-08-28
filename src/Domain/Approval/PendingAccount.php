<?php

namespace QOR\App\Domain\Approval;

use QOR\App\Domain\Approval\Enum\ApprovalDecidableType;

final class PendingAccount
{
    public function __construct(
        public readonly ApprovalDecidableType $type,
        public readonly int $id,
    ) {
    }
}
