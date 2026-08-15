<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Promoter;
use App\Models\Venue;
use Illuminate\Support\Facades\Storage;

it('given a cancelled event when an anonymous visitor opens its detail then it returns the event with a cancelled status', function () {
    $event = Event::factory()->for(Venue::factory())->create([
        'status' => EventStatus::Cancelled,
        'start_date_time' => now()->subDay(),
    ]);

    $this->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('data.id', (string) $event->id)
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.bannerStatus', 'cancelled')
        ->assertJsonMissingPath('data.ticketUrl');
});

it('given a published event whose date has passed when its detail is requested then finished is derived live', function () {
    $event = Event::factory()->for(Venue::factory())->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->subWeek(),
    ]);

    $this->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('data.bannerStatus', 'finished')
        ->assertJsonMissingPath('data.ticketUrl');
});

it('given a published upcoming event when its detail is requested then it returns no banner and includes the ticket url', function () {
    $event = Event::factory()->for(Venue::factory())->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDay(),
        'ticket_url' => 'https://example.com/tickets',
    ]);

    $this->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('data.bannerStatus', null)
        ->assertJsonPath('data.ticketUrl', 'https://example.com/tickets');
});

test('given an unknown event id when its detail is requested then it returns not found', function () {
    $this->getJson('/api/events/999999')
        ->assertNotFound()
        ->assertJsonPath('message', 'Evento não encontrado.');
});

test('given an internal-only event status when its detail is requested then it returns not found', function (EventStatus $status) {
    $event = Event::factory()->for(Venue::factory())->create([
        'status' => $status,
        'start_date_time' => now()->addDay(),
    ]);

    $this->getJson("/api/events/{$event->id}")->assertNotFound();
})->with([
    'draft' => EventStatus::Draft,
    'pending approval' => EventStatus::PendingApproval,
    'approved' => EventStatus::Approved,
    'changes requested' => EventStatus::ChangesRequested,
]);

it('given an event with optional fields unset when its detail is requested then those fields are gracefully omitted', function () {
    $venue = Venue::factory()->create([
        'latitude' => null,
        'longitude' => null,
    ]);
    $event = Event::factory()->for($venue)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDay(),
        'end_date_time' => null,
        'price_min' => null,
        'price_max' => null,
        'is_free' => false,
        'age_rating' => null,
    ]);

    $response = $this->getJson("/api/events/{$event->id}");

    $response->assertOk()
        ->assertJsonMissingPath('data.endDateTime')
        ->assertJsonMissingPath('data.price')
        ->assertJsonMissingPath('data.ageRating')
        ->assertJsonMissingPath('data.promoter')
        ->assertJsonMissingPath('data.venue.staticMapUrl');
});

it('given an event with a promoter when its detail is requested then it returns the primary promoter', function () {
    $event = Event::factory()->for(Venue::factory())->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDay(),
    ]);
    $promoter = Promoter::factory()->create(['name' => 'Rock Produções']);
    $event->promoters()->attach($promoter);

    $this->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('data.promoter.name', 'Rock Produções');
});

it('given an event with a stored cover image when its detail is requested then coverImageUrl resolves the public s3 url', function () {
    Storage::fake('s3');
    $event = Event::factory()->for(Venue::factory())->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDay(),
        'cover_image_path' => 'events/covers/cover.jpg',
    ]);

    $this->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('data.coverImageUrl', Storage::disk('s3')->url('events/covers/cover.jpg'));
});

it('given an event with no stored cover image when its detail is requested then coverImageUrl is null', function () {
    $event = Event::factory()->for(Venue::factory())->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDay(),
        'cover_image_path' => null,
    ]);

    $this->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('data.coverImageUrl', null);
});

it('given a venue with coordinates when its detail is requested then it returns a static map url', function () {
    $venue = Venue::factory()->create(['latitude' => -20.3155, 'longitude' => -40.3128]);
    $event = Event::factory()->for($venue)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDay(),
    ]);

    $this->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('data.venue.staticMapUrl', fn (?string $url) => str_contains($url, '-20.3155,-40.3128'));
});
