<?php

use App\Enums\EventStatus;
use App\Models\Artist;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Wednesday — gives stable, unambiguous "hoje"/weekend/next-week boundaries.
    Carbon::setTestNow(Carbon::parse('2026-08-12 10:00:00', 'America/Sao_Paulo'));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('given combined date city genre and artist filters when events are requested then only their intersection is returned', function () {
    $vitoria = Venue::factory()->create(['city' => 'Vitória']);
    $vilaVelha = Venue::factory()->create(['city' => 'Vila Velha']);
    $artist = Artist::factory()->create(['name' => 'Banda Alvo']);
    $otherArtist = Artist::factory()->create(['name' => 'Outra Banda']);

    $match = Event::factory()->for($vitoria)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->setTime(15, 0),
        'genres' => ['rock', 'mpb'],
    ]);
    $match->artists()->attach($artist);

    // Wrong date bucket (tomorrow), otherwise identical.
    $wrongDate = Event::factory()->for($vitoria)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDay()->setTime(15, 0),
        'genres' => ['rock'],
    ]);
    $wrongDate->artists()->attach($artist);

    // Wrong city, otherwise identical.
    $wrongCity = Event::factory()->for($vilaVelha)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->setTime(16, 0),
        'genres' => ['rock'],
    ]);
    $wrongCity->artists()->attach($artist);

    // Wrong genre, otherwise identical.
    $wrongGenre = Event::factory()->for($vitoria)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->setTime(17, 0),
        'genres' => ['samba'],
    ]);
    $wrongGenre->artists()->attach($artist);

    // Wrong artist, otherwise identical.
    $wrongArtist = Event::factory()->for($vitoria)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->setTime(18, 0),
        'genres' => ['rock'],
    ]);
    $wrongArtist->artists()->attach($otherArtist);

    $response = $this->getJson('/api/events?'.http_build_query([
        'date_bucket' => 'hoje',
        'city' => 'Vitória',
        'genres' => ['rock'],
        'artist_id' => $artist->id,
    ]));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', (string) $match->id);
});

it('given a genre filter with multiple selected genres when events are requested then it returns the union not the intersection', function () {
    $venue = Venue::factory()->create();

    $rock = Event::factory()->for($venue)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDay(),
        'genres' => ['rock'],
    ]);
    $samba = Event::factory()->for($venue)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDays(2),
        'genres' => ['samba'],
    ]);
    $reggae = Event::factory()->for($venue)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDays(3),
        'genres' => ['reggae'],
    ]);

    $response = $this->getJson('/api/events?'.http_build_query(['genres' => ['rock', 'samba']]));

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id'))
        ->toContain((string) $rock->id, (string) $samba->id)
        ->not->toContain((string) $reggae->id);
});

test('given an invalid date_bucket or city when events are requested then it returns a validation error', function (string $field, string $value) {
    $this->getJson('/api/events?'.http_build_query([$field => $value]))
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);
})->with([
    'bad date_bucket' => ['date_bucket', 'yesterday'],
    'bad city' => ['city', 'Nowhereville'],
]);

test('given no filters when events are requested then it behaves identically to the plain feed', function () {
    $venue = Venue::factory()->create();
    Event::factory()->for($venue)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDay(),
    ]);

    $this->getJson('/api/events')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('given a matching cancelled event when filters are applied then it remains in the results', function () {
    $venue = Venue::factory()->create(['city' => 'Serra']);
    $cancelled = Event::factory()->for($venue)->create([
        'status' => EventStatus::Cancelled,
        'start_date_time' => now()->addDay(),
    ]);

    $this->getJson('/api/events?'.http_build_query(['city' => 'Serra']))
        ->assertOk()
        ->assertJsonPath('data.0.id', (string) $cancelled->id);
});

it('given an empty-result filter combination when events are requested then it returns the empty contract', function () {
    Venue::factory()->create(['city' => 'Cariacica']);

    $this->getJson('/api/events?'.http_build_query(['city' => 'Cariacica']))
        ->assertOk()
        ->assertExactJson(['data' => [], 'next_cursor' => null]);
});
