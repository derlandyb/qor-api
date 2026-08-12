<?php

namespace App\Http\Resources\Publisher;

use App\Models\EventRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EventRevision */
final class EventRevisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'eventId' => (string) $this->event_id,
            'title' => $this->title,
            'description' => $this->description,
            'coverImageUrl' => $this->cover_image_url,
            'startDateTime' => $this->start_date_time->toIso8601String(),
            'endDateTime' => $this->when($this->end_date_time !== null, fn () => $this->end_date_time->toIso8601String()),
            'venueId' => (string) $this->venue_id,
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
