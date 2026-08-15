<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
final class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            // Always present — null renders as a placeholder image client-side, never omitted.
            'coverImageUrl' => $this->cover_image_url,
            'startDateTime' => $this->start_date_time->toIso8601String(),
            'venue' => new VenueResource($this->whenLoaded('venue')),
            'city' => $this->city,
            'price' => $this->when(
                $this->is_free || ! is_null($this->price_min),
                fn () => [
                    'isFree' => $this->is_free,
                    'min' => $this->price_min !== null ? (float) $this->price_min : null,
                    'max' => $this->price_max !== null ? (float) $this->price_max : null,
                    'currency' => $this->currency,
                ],
            ),
            'ageRating' => $this->when(! is_null($this->age_rating), fn () => $this->age_rating->value),
            'genres' => $this->genres ?? [],
            'ticketUrl' => $this->ticket_url,
            'status' => $this->status->value,
            'isFavorited' => $this->when(
                $request->attributes->has('favoritedEventIds'),
                fn () => $request->attributes->get('favoritedEventIds')->contains($this->id),
            ),
        ];
    }
}
