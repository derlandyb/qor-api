<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Support\Facades\Storage;

const INTEGRATION_CRAWLER_UA = 'Twitterbot/1.0';

test('given real Postgres data when a crawler requests each publicly-resolvable status then correct og tags render end-to-end', function (EventStatus $status) {
    Storage::fake('s3');
    $event = Event::factory()->for(Venue::factory()->create(['city' => 'Vitória', 'name' => 'Teatro Carlos Gomes']))->create([
        'title' => 'Show Integrado',
        'status' => $status,
        'start_date_time' => $status === EventStatus::Published ? now()->addWeek() : now()->subWeek(),
        'cover_image_path' => 'events/covers/cover.jpg',
    ]);

    $response = $this->withHeaders(['User-Agent' => INTEGRATION_CRAWLER_UA])
        ->get("/compartilhar/eventos/{$event->id}");

    $response->assertOk();
    $response->assertSee('og:title', false);
    $response->assertSee('og:image', false);
    $response->assertSee(Storage::disk('s3')->url('events/covers/cover.jpg'), false);
    $response->assertSee('Show Integrado', false);
})->with([
    'published' => EventStatus::Published,
    'cancelled' => EventStatus::Cancelled,
    'finished' => EventStatus::Published,
]);
