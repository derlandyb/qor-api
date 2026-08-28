<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Infrastructure\Persistence\Eloquent\EventModel;
use QOR\App\Infrastructure\Persistence\Eloquent\PromoterModel;
use QOR\App\Infrastructure\Persistence\Eloquent\VenueModel;

class EventSeeder extends Seeder
{
    /**
     * Real (non-lorem-ipsum) Unsplash cover images matched to each genre's vibe,
     * per ARCHITECTURE.md §8.7.
     */
    private const GENRE_IMAGES = [
        'rock' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=1200&q=80',
        'samba' => 'https://images.unsplash.com/photo-1504704911898-68304a7d2807?w=1200&q=80',
        'sertanejo' => 'https://images.unsplash.com/photo-1516873240891-4bf014598ab4?w=1200&q=80',
        'eletronico' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=1200&q=80',
        'reggae' => 'https://images.unsplash.com/photo-1524368535928-5b5e00ddc76b?w=1200&q=80',
        'pagode' => 'https://images.unsplash.com/photo-1514320291840-2e0a9bf2a9ae?w=1200&q=80',
        'mpb' => 'https://images.unsplash.com/photo-1571266028243-d220c9c3b31d?w=1200&q=80',
        'funk' => 'https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?w=1200&q=80',
        'forro' => 'https://images.unsplash.com/photo-1465847899084-d164df4dedc6?w=1200&q=80',
        'hip-hop' => 'https://images.unsplash.com/photo-1475275166152-f1e8005f9854?w=1200&q=80',
    ];

    public function run(): void
    {
        $genres = DB::table('genres')->get(['id', 'slug']);
        $venues = VenueModel::where('approval_status', 'approved')->get();
        $promoters = PromoterModel::where('approval_status', 'approved')->get();
        $cities = City::cases();
        $statuses = EventStatus::cases();

        $i = 0;

        foreach ($genres as $genre) {
            foreach ($statuses as $status) {
                $city = $cities[$i % count($cities)];
                $useVenue = $i % 2 === 0;

                $organizer = $useVenue ? $venues->get($i % $venues->count()) : $promoters->get($i % $promoters->count());

                if ($organizer === null) {
                    continue;
                }

                $isFree = $i % 3 === 0;

                EventModel::factory()->create([
                    'created_by_type' => $useVenue ? EventCreatedByType::VenueAdmin->value : EventCreatedByType::Promoter->value,
                    'created_by_id' => $organizer->id,
                    'city' => $city->value,
                    'genre_id' => $genre->id,
                    'status' => $status->value,
                    'starts_at' => $status === EventStatus::Ended
                        ? now()->subDays(random_int(1, 30))
                        : now()->addDays(random_int(1, 90)),
                    'is_free' => $isFree,
                    'ticket_url' => $isFree ? null : 'https://ingressos.example.com/evento-'.($i + 1),
                    'cover_image_url' => self::GENRE_IMAGES[$genre->slug] ?? self::GENRE_IMAGES['rock'],
                ]);

                $i++;
            }
        }
    }
}
