<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

it('given a 100-event batch with 3 malformed rows when analytics are ingested then exactly 97 rows are inserted', function () {
    $events = array_fill(0, 97, ['eventName' => 'map_opened', 'clientTimestamp' => now()->toIso8601String()]);
    $events[] = ['eventName' => 'not_a_real_event', 'clientTimestamp' => now()->toIso8601String()];
    $events[] = ['eventName' => 'map_opened', 'clientTimestamp' => null];
    $events[] = ['clientTimestamp' => now()->toIso8601String()];

    $this->postJson('/api/analytics/events', ['events' => $events])
        ->assertStatus(202)
        ->assertExactJson(['accepted' => 97, 'rejected' => 3]);

    expect(DB::table('analytics_events')->count())->toBe(97);
});

it('given a valid sanctum token when analytics are ingested then the row is attributed to that user', function () {
    $fan = User::factory()->create();
    Sanctum::actingAs($fan);

    $this->postJson('/api/analytics/events', [
        'events' => [['eventName' => 'event_favorited', 'properties' => ['eventId' => '1'], 'clientTimestamp' => now()->toIso8601String()]],
    ])->assertStatus(202);

    expect(DB::table('analytics_events')->first()->user_id)->toBe($fan->id);
});

it('given an anonymous request when analytics are ingested then user_id is null', function () {
    $this->postJson('/api/analytics/events', [
        'events' => [['eventName' => 'search_performed', 'properties' => ['query' => 'rock', 'resultCount' => 2], 'clientTimestamp' => now()->toIso8601String()]],
    ])->assertStatus(202);

    expect(DB::table('analytics_events')->first()->user_id)->toBeNull();
});
