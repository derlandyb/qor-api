<?php

namespace App\Http\Resources;

use App\Models\Artist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Artist */
final class ArtistOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
        ];
    }
}
