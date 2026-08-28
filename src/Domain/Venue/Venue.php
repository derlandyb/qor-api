<?php

namespace QOR\App\Domain\Venue;

use InvalidArgumentException;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Domain\Shared\Enum\City;

final class Venue
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $venueAdminUserId,
        public readonly string $name,
        public readonly string $description,
        public readonly string $address,
        public readonly City $city,
        public readonly string $contactPhone,
        public readonly string $contactEmail,
        public readonly ApprovalStatus $approvalStatus = ApprovalStatus::PendingApproval,
        public readonly ?string $imageUrl = null,
    ) {
        if ($this->name === '') {
            throw new InvalidArgumentException('O nome do estabelecimento não pode ser vazio.');
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
