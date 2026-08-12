<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Venue;

it('given an anonymous visitor when map events are requested then published upcoming events with coordinates are returned without location permission', function () {
    $venue = Venue::factory()->create(['latitude' => -20.31, 'longitude' => -40.31]);

    $draft = Event::factory()->for($venue)->create([
        'status' => EventStatus::Draft,
        'start_date_time' => now()->addDay(),
    ]);
    $pendingApproval = Event::factory()->for($venue)->create([
        'status' => EventStatus::PendingApproval,
        'start_date_time' => now()->addDay(),
    ]);
    $finished = Event::factory()->for($venue)->create([
        'status' => EventStatus::Finished,
        'start_date_time' => now()->subWeek(),
    ]);
    $published = Event::factory()->for($venue)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDay(),
    ]);
    $cancelled = Event::factory()->for($venue)->create([
        'status' => EventStatus::Cancelled,
        'start_date_time' => now()->addDays(2),
    ]);

    // No Sanctum token attached anywhere in this test — asserts anonymous, permission-free access.
    $response = $this->getJson('/api/events/map');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonMissingPath('next_cursor')
        ->assertJsonMissingPath('cursor');
    expect(collect($response->json('data'))->pluck('id'))
        ->toContain((string) $published->id, (string) $cancelled->id)
        ->not->toContain((string) $draft->id, (string) $pendingApproval->id, (string) $finished->id);
});

test('given an empty database when map events are requested then it returns an empty contract', function () {
    $this->getJson('/api/events/map')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

test('given an invalid date_bucket or city when map events are requested then it returns a validation error', function (string $field, string $value) {
    $this->getJson('/api/events/map?'.http_build_query([$field => $value]))
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);
})->with([
    'bad date_bucket' => ['date_bucket', 'yesterday'],
    'bad city' => ['city', 'Nowhereville'],
]);
