<?php

namespace QOR\App\Infrastructure\Persistence;

use QOR\App\Domain\Venue\Venue;
use QOR\App\Domain\Venue\VenueRepository;
use QOR\App\Infrastructure\Persistence\Eloquent\VenueModel;

class EloquentVenueRepository implements VenueRepository
{
    public function findById(int $id): ?Venue
    {
        $model = VenueModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function save(Venue $venue): Venue
    {
        $model = $venue->id !== null ? VenueModel::findOrFail($venue->id) : new VenueModel();

        $model->fill([
            'venue_admin_user_id' => $venue->venueAdminUserId,
            'name' => $venue->name,
            'description' => $venue->description,
            'address' => $venue->address,
            'city' => $venue->city->value,
            'contact_phone' => $venue->contactPhone,
            'contact_email' => $venue->contactEmail,
            'image_url' => $venue->imageUrl,
            'approval_status' => $venue->approvalStatus->value,
        ]);

        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        VenueModel::destroy($id);
    }

    private function toDomain(VenueModel $model): Venue
    {
        return new Venue(
            id: $model->id,
            venueAdminUserId: $model->venue_admin_user_id,
            name: $model->name,
            description: $model->description,
            address: $model->address,
            city: $model->city,
            contactPhone: $model->contact_phone,
            contactEmail: $model->contact_email,
            approvalStatus: $model->approval_status,
            imageUrl: $model->image_url,
        );
    }
}
