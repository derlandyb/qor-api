<?php

namespace QOR\App\Domain\Promoter\UseCase;

use InvalidArgumentException;
use QOR\App\Domain\Promoter\Promoter;
use QOR\App\Domain\Promoter\PromoterRepository;

final class EditPromoterProfile
{
    public function __construct(
        private readonly PromoterRepository $promoters,
    ) {
    }

    /**
     * Partial edit: only the provided (non-null) fields are applied, the
     * rest of the promoter's current values are kept as-is. Approval status
     * is never touched here — a profile edit cannot change it.
     */
    public function execute(
        int $promoterId,
        ?string $name = null,
        ?string $contactPhone = null,
        ?string $contactEmail = null,
        ?string $instagram = null,
        ?string $tiktok = null,
    ): Promoter {
        $promoter = $this->promoters->findById($promoterId);

        if ($promoter === null) {
            throw new InvalidArgumentException('Promoter não encontrado.');
        }

        $updated = new Promoter(
            id: $promoter->id,
            userId: $promoter->userId,
            name: $name ?? $promoter->name,
            contactPhone: $contactPhone ?? $promoter->contactPhone,
            contactEmail: $contactEmail ?? $promoter->contactEmail,
            approvalStatus: $promoter->approvalStatus,
            instagram: $instagram ?? $promoter->instagram,
            tiktok: $tiktok ?? $promoter->tiktok,
        );

        return $this->promoters->save($updated);
    }
}
