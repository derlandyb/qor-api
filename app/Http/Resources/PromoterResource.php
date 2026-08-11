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
            'verificationStatus' => $this->verification_status->value,
        ];
    }
}
