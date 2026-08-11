<?php

use App\Enums\EventStatus;
use App\Models\Artist;
use App\Models\Event;
use App\Models\Venue;

it('given an accented multiword query when events are searched then matching artist venue genre and title events are returned', function () {
    $venue = Venue::factory()->create(['name' => 'Clube Náutico']);
    $artist = Artist::factory()->create(['name' => 'Jorge & o Bando']);

    $titleMatch = Event::factory()->for($venue)->create([
        'title' => 'Noite do Forró',
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDay(),
        'genres' => ['samba'],
    ]);
    $venueMatch = Event::factory()->for($venue)->create([
        'title' => 'Show de Rock',
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDays(2),
        'genres' => ['rock'],
    ]);
    $artistMatch = Event::factory()->for(Venue::factory()->create())->create([
        'title' => 'Especial de Verão',
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDays(3),
        'genres' => ['mpb'],
    ]);
    $artistMatch->artists()->attach($artist);
    $genreMatch = Event::factory()->for(Venue::factory()->create())->create([
        'title' => 'Festival da Praia',
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDays(4),
        'genres' => ['forró'],
    ]);
    $noMatch = Event::factory()->for(Venue::factory()->create())->create([
        'title' => 'Tributo ao Reggae',
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDays(5),
        'genres' => ['reggae'],
    ]);

    // "forro nautico" (unaccented, multiword): both terms must match, any field, case/accent-insensitive.
    $byTitleAndVenue = $this->getJson('/api/events?q='.urlencode('forro nautico'));
    $byTitleAndVenue->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', (string) $titleMatch->id);

    $byVenue = $this->getJson('/api/events?q=nautico');
    $byVenue->assertOk()
        ->assertJsonCount(2, 'data');
    expect(collect($byVenue->json('data'))->pluck('id'))
        ->toContain((string) $titleMatch->id, (string) $venueMatch->id)
        ->not->toContain((string) $noMatch->id);

    $byArtist = $this->getJson('/api/events?q=jorge');
    $byArtist->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', (string) $artistMatch->id);

    $byGenre = $this->getJson('/api/events?q=forro');
    $byGenre->assertOk()
        ->assertJsonCount(2, 'data');
    expect(collect($byGenre->json('data'))->pluck('id'))
        ->toContain((string) $titleMatch->id, (string) $genreMatch->id);
});

it('given a query when events are searched then an exact match ranks before a partial match', function () {
    $venue = Venue::factory()->create();
    $partial = Event::factory()->for($venue)->create([
        'title' => 'Rock in Vitória',
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDay(),
        'genres' => ['samba'],
    ]);
    $exact = Event::factory()->for($venue)->create([
        'title' => 'Rock',
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDays(2),
        'genres' => ['samba'],
    ]);

    $this->getJson('/api/events?q=Rock')
        ->assertOk()
        ->assertJsonPath('data.0.id', (string) $exact->id)
        ->assertJsonPath('data.1.id', (string) $partial->id);
});

it('given a search query when there are more matches than one page then cursor pagination still returns every match exactly once', function () {
    $venue = Venue::factory()->create();
    Event::factory()->for($venue)->count(25)->sequence(
        fn ($sequence) => ['start_date_time' => now()->addMinutes($sequence->index + 1)],
    )->create([
        'title' => 'Rock Night',
        'status' => EventStatus::Published,
    ]);

    $seenIds = [];
    $cursor = null;

    do {
        $url = '/api/events?q=rock&limit=10'.($cursor !== null ? '&cursor='.$cursor : '');
        $response = $this->getJson($url)->assertOk();
        $seenIds = [...$seenIds, ...collect($response->json('data'))->pluck('id')->all()];
        $cursor = $response->json('next_cursor');
    } while ($cursor !== null);

    expect($seenIds)->toHaveCount(25)
        ->and(array_unique($seenIds))->toHaveCount(25);
});

test('given no q param when events are searched then it behaves identically to the plain feed', function () {
    $venue = Venue::factory()->create();
    Event::factory()->for($venue)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDay(),
    ]);

    $this->getJson('/api/events')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('given an empty or whitespace q when events are searched then it behaves identically to no q at all', function (string $q) {
    $venue = Venue::factory()->create();
    Event::factory()->for($venue)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDay(),
    ]);

    $this->getJson('/api/events?q='.urlencode($q))
        ->assertOk()
        ->assertJsonCount(1, 'data');
})->with([
    'empty' => '',
    'whitespace' => '   ',
]);

it('given a matching cancelled event when events are searched then it remains in the results', function () {
    $venue = Venue::factory()->create();
    $cancelled = Event::factory()->for($venue)->create([
        'title' => 'Rock Cancelado',
        'status' => EventStatus::Cancelled,
        'start_date_time' => now()->addDay(),
    ]);

    $this->getJson('/api/events?q=rock')
        ->assertOk()
        ->assertJsonPath('data.0.id', (string) $cancelled->id);
});

it('given a matching finished event when events are searched then it is excluded', function () {
    $venue = Venue::factory()->create();
    Event::factory()->for($venue)->create([
        'title' => 'Rock do Passado',
        'status' => EventStatus::Finished,
        'start_date_time' => now()->subWeek(),
    ]);

    $this->getJson('/api/events?q=rock')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
