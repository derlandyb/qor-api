<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

it('given a batch containing a malformed event when analytics are ingested then valid rows are accepted and the batch returns 202', function () {
    $response = $this->postJson('/api/analytics/events', [
        'events' => [
            ['eventName' => 'search_performed', 'properties' => ['query' => 'rock', 'resultCount' => 4], 'clientTimestamp' => now()->toIso8601String()],
            ['eventName' => 'not_a_real_event', 'clientTimestamp' => now()->toIso8601String()],
            ['eventName' => 'map_opened', 'clientTimestamp' => null],
        ],
    ]);

    $response->assertStatus(202)->assertExactJson(['accepted' => 1, 'rejected' => 2]);
    expect(DB::table('analytics_events')->count())->toBe(1);
});

it('given no sanctum token when analytics are ingested then the anonymous batch still succeeds', function () {
    $this->postJson('/api/analytics/events', [
        'events' => [
            ['eventName' => 'event_viewed', 'properties' => ['eventId' => '1', 'source' => 'feed'], 'clientTimestamp' => now()->toIso8601String()],
        ],
    ])->assertStatus(202)->assertExactJson(['accepted' => 1, 'rejected' => 0]);

    expect(DB::table('analytics_events')->first()->user_id)->toBeNull();
});

it('given a valid sanctum token when analytics are ingested then the row is attributed to that user', function () {
    $fan = User::factory()->create();
    Sanctum::actingAs($fan);

    $this->postJson('/api/analytics/events', [
        'events' => [
            ['eventName' => 'event_favorited', 'properties' => ['eventId' => '1'], 'clientTimestamp' => now()->toIso8601String()],
        ],
    ])->assertStatus(202);

    expect(DB::table('analytics_events')->first()->user_id)->toBe($fan->id);
});

it('given a batch exceeding 100 events when analytics are ingested then it is truncated, not rejected outright', function () {
    $events = array_fill(0, 150, ['eventName' => 'map_opened', 'clientTimestamp' => now()->toIso8601String()]);

    $this->postJson('/api/analytics/events', ['events' => $events])
        ->assertStatus(202)
        ->assertExactJson(['accepted' => 100, 'rejected' => 0]);
});

it('given a non-array events body when analytics are ingested then it returns 422', function () {
    $this->postJson('/api/analytics/events', ['events' => 'not-an-array'])
        ->assertStatus(422);
});

it('given an empty events array when analytics are ingested then it returns an empty accepted batch', function () {
    $this->postJson('/api/analytics/events', ['events' => []])
        ->assertStatus(202)
        ->assertExactJson(['accepted' => 0, 'rejected' => 0]);
});

it('given properties omitted when a valid event is ingested then it is accepted with an empty properties object', function () {
    $this->postJson('/api/analytics/events', [
        'events' => [
            ['eventName' => 'map_opened', 'clientTimestamp' => now()->toIso8601String()],
        ],
    ])->assertStatus(202)->assertExactJson(['accepted' => 1, 'rejected' => 0]);

    expect(json_decode(DB::table('analytics_events')->first()->properties, true))->toBe([]);
});
