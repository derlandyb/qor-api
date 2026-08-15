<?php

namespace App\Http\Resources\Publisher;

use App\Http\Resources\VenueResource;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'coverImageUrl' => $this->cover_image_path !== null ? Storage::disk('s3')->url($this->cover_image_path) : null,
            'startDateTime' => $this->start_date_time?->toIso8601String(),
            'endDateTime' => $this->end_date_time?->toIso8601String(),
            'venue' => new VenueResource($this->whenLoaded('venue')),
            // Always present, even for a Draft with no price state yet — this.when() would
            // omit the key entirely rather than return null, which crashed the admin edit
            // form's `price === null` guard (it saw `undefined`, not `null`).
            'price' => [
                'isFree' => $this->is_free,
                'min' => $this->price_min !== null ? (float) $this->price_min : null,
                'max' => $this->price_max !== null ? (float) $this->price_max : null,
                'currency' => $this->currency,
            ],
            'ageRating' => $this->when(! is_null($this->age_rating), fn () => $this->age_rating->value),
            'genres' => $this->genres ?? [],
            'ticketUrl' => $this->ticket_url,
            'status' => $this->status->value,
            'reviewerFeedback' => $this->reviewer_feedback,
            // A Venue account's dashboard scope is "events at my venue," which legitimately
            // includes events a different Promoter account submitted — this makes that visible
            // in the UI instead of reading as an unexplained cross-account leak.
            'promoterNames' => $this->whenLoaded('promoters', fn () => $this->promoters->pluck('name')->all()),
        ];
    }
}
