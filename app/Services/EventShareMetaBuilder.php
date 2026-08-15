<?php

namespace App\Services;

use App\Enums\BannerStatus;
use App\Models\Event;
use Illuminate\Support\Facades\Storage;

final class EventShareMetaBuilder
{
    public static function build(Event $event): ShareMeta
    {
        $bannerStatus = EventBannerStatusResolver::resolve($event);

        $dateLabel = $event->start_date_time->locale('pt_BR')->translatedFormat('D, d \d\e M \à\s H:i');
        $venueLabel = "{$event->venue->name}, {$event->city}";

        $suffix = match ($bannerStatus) {
            BannerStatus::Cancelled => ' (Evento cancelado)',
            BannerStatus::Finished => ' (Este evento já aconteceu)',
            default => '',
        };

        return new ShareMeta(
            title: $event->title.$suffix,
            // SHARE-004: minimum shareable content is title + cover image + date/venue snippet.
            description: "{$dateLabel} · {$venueLabel}",
            // Omitted (not a broken <img>) when unset — Edge Cases: missing cover image still yields a valid link.
            imageUrl: $event->cover_image_path !== null ? Storage::disk('s3')->url($event->cover_image_path) : null,
            bannerStatus: $bannerStatus,
        );
    }
}
