<?php

namespace App\Http\Resources;

use App\Models\Promoter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Promoter */
final class PromoterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'imageUrl' => $this->image_url,
            'description' => $this->description,
            // {"instagram": "...", "whatsapp": "..."} — each key individually omitted client-side when unset.
            'socialLinks' => $this->social_links,
            'contactPhone' => $this->contact_phone,
            'contactEmail' => $this->contact_email,
            'verificationStatus' => $this->verification_status->value,
        ];
    }
}
