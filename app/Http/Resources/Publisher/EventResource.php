<?php

namespace App\Http\Resources\Publisher;

use App\Http\Resources\VenueResource;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The publisher-facing (owner's own) event representation — unlike the public EventResource,
 * this exposes the raw lifecycle `status` (no banner-status collapsing) and `reviewerFeedback`,
 * and tolerates a Draft's not-yet-filled-in nullable fields.
 *
 * @mixin Event
 */
final class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'coverImageUrl' => $this->cover_image_url,
            'startDateTime' => $this->start_date_time?->toIso8601String(),
            'endDateTime' => $this->end_date_time?->toIso8601String(),
            'venue' => new VenueResource($this->whenLoaded('venue')),
            'price' => $this->when(
                $this->is_free || ! is_null($this->price_min),
                fn () => [
                    'isFree' => $this->is_free,
                    'min' => $this->price_min,
                    'max' => $this->price_max,
                    'currency' => $this->currency,
                ],
            ),
            'ageRating' => $this->when(! is_null($this->age_rating), fn () => $this->age_rating->value),
            'genres' => $this->genres ?? [],
            'ticketUrl' => $this->ticket_url,
            'status' => $this->status->value,
            'reviewerFeedback' => $this->reviewer_feedback,
        ];
    }
}
