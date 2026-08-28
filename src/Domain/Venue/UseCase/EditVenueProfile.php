<?php

namespace QOR\App\Domain\Venue\UseCase;

use InvalidArgumentException;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\Shared\FileUploadPort;
use QOR\App\Domain\Shared\UploadableFile;
use QOR\App\Domain\Venue\Venue;
use QOR\App\Domain\Venue\VenueRepository;

final class EditVenueProfile
{
    public function __construct(
        private readonly VenueRepository $venues,
        private readonly FileUploadPort $fileUpload,
    ) {
    }

    /**
     * Partial edit: only the provided (non-null) fields are applied, the
     * rest of the venue's current values are kept as-is. Approval status is
     * never touched here — a profile edit cannot change it.
     */
    public function execute(
        int $venueId,
        ?string $name = null,
        ?string $description = null,
        ?string $address = null,
        ?City $city = null,
        ?string $contactPhone = null,
        ?string $contactEmail = null,
        ?UploadableFile $image = null,
    ): Venue {
        $venue = $this->venues->findById($venueId);

        if ($venue === null) {
            throw new InvalidArgumentException('Venue não encontrada.');
        }

        $imageUrl = $venue->imageUrl;
        if ($image !== null) {
            $imageUrl = $this->fileUpload->upload($image, 'venues/images');
        }

        $updated = new Venue(
            id: $venue->id,
            venueAdminUserId: $venue->venueAdminUserId,
            name: $name ?? $venue->name,
            description: $description ?? $venue->description,
            address: $address ?? $venue->address,
            city: $city ?? $venue->city,
            contactPhone: $contactPhone ?? $venue->contactPhone,
            contactEmail: $contactEmail ?? $venue->contactEmail,
            approvalStatus: $venue->approvalStatus,
            imageUrl: $imageUrl,
        );

        return $this->venues->save($updated);
    }
}
