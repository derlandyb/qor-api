<?php

use App\Enums\EventStatus;
use App\Models\Artist;
use App\Models\Event;
use App\Models\Venue;

it('given events with missing or out of range venue coordinates when map events are requested then they are excluded while remaining in the plain feed', function () {
    $valid = Event::factory()->for(Venue::factory()->create(['latitude' => -20.31, 'longitude' => -40.31]))->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDay(),
    ]);
    $missingCoordinates = Event::factory()->for(Venue::factory()->create(['latitude' => null, 'longitude' => null]))->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDay(),
    ]);
    $outOfRangeCoordinates = Event::factory()->for(Venue::factory()->create(['latitude' => 95, 'longitude' => -40.31]))->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDay(),
    ]);

    $mapResponse = $this->getJson('/api/events/map');
    $feedResponse = $this->getJson('/api/events');

    $mapResponse->assertOk();
    expect(collect($mapResponse->json('data'))->pluck('id'))
        ->toContain((string) $valid->id)
        ->not->toContain((string) $missingCoordinates->id, (string) $outOfRangeCoordinates->id);

    $feedResponse->assertOk();
    expect(collect($feedResponse->json('data'))->pluck('id'))
        ->toContain((string) $valid->id, (string) $missingCoordinates->id, (string) $outOfRangeCoordinates->id);
});

it('given combined date city genre and artist filters when map events are requested then only their intersection is returned', function () {
    Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-08-12 10:00:00', 'America/Sao_Paulo'));

    $vitoria = Venue::factory()->create(['city' => 'Vitória', 'latitude' => -20.31, 'longitude' => -40.31]);
    $vilaVelha = Venue::factory()->create(['city' => 'Vila Velha', 'latitude' => -20.33, 'longitude' => -40.29]);
    $artist = Artist::factory()->create();
    $otherArtist = Artist::factory()->create();

    $match = Event::factory()->for($vitoria)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->setTime(15, 0),
        'genres' => ['rock'],
    ]);
    $match->artists()->attach($artist);

    $wrongCity = Event::factory()->for($vilaVelha)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->setTime(16, 0),
        'genres' => ['rock'],
    ]);
    $wrongCity->artists()->attach($artist);

    $wrongArtist = Event::factory()->for($vitoria)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->setTime(17, 0),
        'genres' => ['rock'],
    ]);
    $wrongArtist->artists()->attach($otherArtist);

    $response = $this->getJson('/api/events/map?'.http_build_query([
        'date_bucket' => 'hoje',
        'city' => 'Vitória',
        'genres' => ['rock'],
        'artist_id' => $artist->id,
    ]));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', (string) $match->id);

    Carbon\Carbon::setTestNow();
});

it('given more events than the configured marker cap when map events are requested then the result is truncated soonest first', function () {
    config(['map.max_markers' => 3]);
    $venue = Venue::factory()->create(['latitude' => -20.31, 'longitude' => -40.31]);

    $events = Event::factory()->for($venue)->count(5)->sequence(
        fn ($sequence) => ['start_date_time' => now()->addMinutes($sequence->index + 1)],
    )->create(['status' => EventStatus::Published]);

    $response = $this->getJson('/api/events/map');

    $response->assertOk()->assertJsonCount(3, 'data');
    expect(collect($response->json('data'))->pluck('id')->all())
        ->toBe($events->take(3)->pluck('id')->map(fn ($id) => (string) $id)->all());
});
