<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Services\EventBannerStatusResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Event */
final class EventDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $bannerStatus = EventBannerStatusResolver::resolve($this->resource);

        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'coverImageUrl' => $this->cover_image_path !== null ? Storage::disk('s3')->url($this->cover_image_path) : null,
            'startDateTime' => $this->start_date_time->toIso8601String(),
            'endDateTime' => $this->when($this->end_date_time !== null, fn () => $this->end_date_time->toIso8601String()),
            'venue' => new VenueDetailResource($this->whenLoaded('venue')),
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
            // Omitted (not just hidden) once cancelled/finished — defense in depth against a client rendering a stale link.
            'ticketUrl' => $this->when($bannerStatus === null, fn () => $this->ticket_url),
            'status' => $this->status->value,
            'bannerStatus' => $bannerStatus?->value,
            'promoter' => $this->when(
                $this->relationLoaded('promoters') && $this->promoters->isNotEmpty(),
                fn () => new PromoterResource($this->promoters->first()),
            ),
            'isFavorited' => $this->when(
                $request->attributes->has('favoritedEventIds'),
                fn () => $request->attributes->get('favoritedEventIds')->contains($this->id),
            ),
        ];
    }
}
