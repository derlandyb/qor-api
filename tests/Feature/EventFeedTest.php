<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Venue;

it('given an anonymous visitor when the feed loads then it returns upcoming published events soonest first', function () {
    $venue = Venue::factory()->create();

    Event::factory()->for($venue)->create([
        'status' => EventStatus::Draft,
        'start_date_time' => now()->addDay(),
    ]);
    Event::factory()->for($venue)->create([
        'status' => EventStatus::PendingApproval,
        'start_date_time' => now()->addDay(),
    ]);
    Event::factory()->for($venue)->create([
        'status' => EventStatus::Finished,
        'start_date_time' => now()->subWeek(),
    ]);
    $published = Event::factory()->for($venue)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDays(2),
    ]);
    $cancelled = Event::factory()->for($venue)->create([
        'status' => EventStatus::Cancelled,
        'start_date_time' => now()->addDay(),
    ]);

    $response = $this->getJson('/api/events');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', (string) $cancelled->id)
        ->assertJsonPath('data.1.id', (string) $published->id);
});

test('given an empty database when the feed loads then it returns an empty contract', function () {
    $this->getJson('/api/events')
        ->assertOk()
        ->assertExactJson(['data' => [], 'next_cursor' => null]);
});

test('given an event with optional fields unset when the feed loads then those fields are omitted, not null', function () {
    $venue = Venue::factory()->create();
    Event::factory()->for($venue)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDay(),
        'price_min' => null,
        'price_max' => null,
        'is_free' => false,
        'age_rating' => null,
    ]);

    $response = $this->getJson('/api/events');

    $response->assertOk()
        ->assertJsonMissingPath('data.0.price')
        ->assertJsonMissingPath('data.0.ageRating');
});

test('given an out-of-range or malformed limit when the feed loads then it returns a validation error', function (string $limit) {
    $this->getJson('/api/events?limit='.$limit)
        ->assertStatus(422)
        ->assertJsonValidationErrors('limit');
})->with([
    'zero' => '0',
    'negative' => '-1',
    'non-numeric' => 'abc',
    'over the cap' => '51',
]);

test('given a valid custom limit when the feed loads then it respects the requested page size', function () {
    $venue = Venue::factory()->create();
    Event::factory()->for($venue)->count(3)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDay(),
    ]);

    $this->getJson('/api/events?limit=1')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
