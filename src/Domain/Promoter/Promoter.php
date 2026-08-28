<?php

namespace QOR\App\Domain\Promoter;

use InvalidArgumentException;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;

final class Promoter
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly string $name,
        public readonly string $contactPhone,
        public readonly string $contactEmail,
        public readonly ApprovalStatus $approvalStatus = ApprovalStatus::PendingApproval,
        public readonly ?string $instagram = null,
        public readonly ?string $tiktok = null,
    ) {
        if ($this->name === '') {
            throw new InvalidArgumentException('O nome do produtor não pode ser vazio.');
        }

        if (! filter_var($this->contactEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("E-mail de contato inválido: {$this->contactEmail}");
        }
    }

    public function canPublish(): bool
    {
        return $this->approvalStatus === ApprovalStatus::Approved;
    }
}
