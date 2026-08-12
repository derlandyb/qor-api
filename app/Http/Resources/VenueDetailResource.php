<?php

namespace App\Http\Resources;

use App\Models\Venue;
use App\Services\StaticMapUrlBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Venue */
final class VenueDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'imageUrl' => $this->image_url,
            'description' => $this->description,
            'address' => $this->address,
            'city' => $this->city,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            // DETAIL-004: present only when coordinates exist; absence means "render address text only, no map element".
            'staticMapUrl' => $this->when(
                $this->latitude !== null && $this->longitude !== null,
                fn () => StaticMapUrlBuilder::build((float) $this->latitude, (float) $this->longitude),
            ),
            'contactPhone' => $this->contact_phone,
            'contactEmail' => $this->contact_email,
            'socialLinks' => $this->social_links,
            'verificationStatus' => $this->verification_status->value,
        ];
    }
}
