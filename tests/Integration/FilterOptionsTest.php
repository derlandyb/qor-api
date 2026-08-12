<?php

use App\Enums\EventStatus;
use App\Models\Artist;
use App\Models\Event;
use App\Models\Venue;

it('given events across several statuses when genre options are requested then only published upcoming genres are returned deduped and sorted', function () {
    $venue = Venue::factory()->create();

    Event::factory()->for($venue)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDay(),
        'genres' => ['rock', 'samba'],
    ]);
    Event::factory()->for($venue)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDays(2),
        'genres' => ['samba', 'forró'],
    ]);
    Event::factory()->for($venue)->create([
        'status' => EventStatus::Cancelled,
        'start_date_time' => now()->addDay(),
        'genres' => ['reggae'],
    ]);
    Event::factory()->for($venue)->create([
        'status' => EventStatus::Draft,
        'start_date_time' => now()->addDay(),
        'genres' => ['pop'],
    ]);
    Event::factory()->for($venue)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->subDay(),
        'genres' => ['mpb'],
    ]);

    $response = $this->getJson('/api/filter-options/genres');

    $response->assertOk();
    expect($response->json('data'))->toBe(['forró', 'rock', 'samba']);
});

test('given no upcoming published events when genre options are requested then it returns an empty list', function () {
    $this->getJson('/api/filter-options/genres')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

it('given artists across qualifying and non-qualifying events when artist options are requested then only artists with a published upcoming event are returned', function () {
    $venue = Venue::factory()->create();

    $qualifying = Artist::factory()->create(['name' => 'Zeca e Banda']);
    $cancelledOnly = Artist::factory()->create(['name' => 'Anônimo']);
    $draftOnly = Artist::factory()->create(['name' => 'Rascunho']);

    $publishedEvent = Event::factory()->for($venue)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDay(),
    ]);
    $publishedEvent->artists()->attach($qualifying);

    $cancelledEvent = Event::factory()->for($venue)->create([
        'status' => EventStatus::Cancelled,
        'start_date_time' => now()->addDay(),
    ]);
    $cancelledEvent->artists()->attach($cancelledOnly);

    $draftEvent = Event::factory()->for($venue)->create([
        'status' => EventStatus::Draft,
        'start_date_time' => now()->addDay(),
    ]);
    $draftEvent->artists()->attach($draftOnly);

    $response = $this->getJson('/api/filter-options/artists');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', (string) $qualifying->id)
        ->assertJsonPath('data.0.name', 'Zeca e Banda');
});

test('given no qualifying artists when artist options are requested then it returns an empty list', function () {
    $this->getJson('/api/filter-options/artists')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});
