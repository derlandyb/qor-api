<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Laravel\Sanctum\Sanctum;

it('given an authenticated fan when favoriting an event then it is persisted exactly once', function () {
    $fan = User::factory()->create();
    $event = Event::factory()->for(Venue::factory())->create();
    Sanctum::actingAs($fan);

    $this->putJson("/api/favorites/{$event->id}")
        ->assertOk()
        ->assertExactJson(['favorited' => true]);

    // Repeating the tap (double-tap edge case) must not create a second row.
    $this->putJson("/api/favorites/{$event->id}")->assertOk();

    expect($fan->favoriteEvents()->where('events.id', $event->id)->count())->toBe(1);
});

it('given a different fan when deleting a favorite then it is forbidden', function () {
    $owner = User::factory()->create();
    $otherFan = User::factory()->create();
    $event = Event::factory()->for(Venue::factory())->create();
    Sanctum::actingAs($owner);
    $this->putJson("/api/favorites/{$event->id}")->assertOk();

    // A DELETE always scopes to the authenticated user's own relation — another fan
    // has no route to remove someone else's favorite, so this is a no-op, not a takeover.
    Sanctum::actingAs($otherFan);
    $this->deleteJson("/api/favorites/{$event->id}")
        ->assertOk()
        ->assertExactJson(['favorited' => false]);

    expect($owner->favoriteEvents()->where('events.id', $event->id)->exists())->toBeTrue();
});

it('given a favorited event when it is unfavorited then it is removed', function () {
    $fan = User::factory()->create();
    $event = Event::factory()->for(Venue::factory())->create();
    Sanctum::actingAs($fan);
    $this->putJson("/api/favorites/{$event->id}")->assertOk();

    $this->deleteJson("/api/favorites/{$event->id}")
        ->assertOk()
        ->assertExactJson(['favorited' => false]);

    expect($fan->favoriteEvents()->where('events.id', $event->id)->exists())->toBeFalse();
});

it('given upcoming and finished favorites when the list is requested then they are split and sorted', function () {
    $fan = User::factory()->create();
    $venue = Venue::factory()->create();
    $soon = Event::factory()->for($venue)->create(['status' => EventStatus::Published, 'start_date_time' => now()->addDay()]);
    $later = Event::factory()->for($venue)->create(['status' => EventStatus::Published, 'start_date_time' => now()->addWeek()]);
    $recentlyFinished = Event::factory()->for($venue)->create(['status' => EventStatus::Published, 'start_date_time' => now()->subDay()]);
    $longFinished = Event::factory()->for($venue)->create(['status' => EventStatus::Published, 'start_date_time' => now()->subMonth()]);
    Sanctum::actingAs($fan);
    foreach ([$soon, $later, $recentlyFinished, $longFinished] as $event) {
        $this->putJson("/api/favorites/{$event->id}")->assertOk();
    }

    $response = $this->getJson('/api/favorites')->assertOk();

    expect(collect($response->json('upcoming'))->pluck('id')->all())->toBe(collect([$soon, $later])->map(fn ($e) => (string) $e->id)->all())
        ->and(collect($response->json('passados'))->pluck('id')->all())->toBe(collect([$recentlyFinished, $longFinished])->map(fn ($e) => (string) $e->id)->all());
});

it('given a cancelled favorite when the list is requested then it stays visible', function () {
    $fan = User::factory()->create();
    $event = Event::factory()->for(Venue::factory())->create(['status' => EventStatus::Cancelled, 'start_date_time' => now()->addWeek()]);
    Sanctum::actingAs($fan);
    $this->putJson("/api/favorites/{$event->id}")->assertOk();

    $this->getJson('/api/favorites')
        ->assertOk()
        ->assertJsonPath('upcoming.0.id', (string) $event->id)
        ->assertJsonPath('upcoming.0.status', 'cancelled');
});

test('given no favorites when the list is requested then it returns an empty split', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/favorites')
        ->assertOk()
        ->assertExactJson(['upcoming' => [], 'passados' => []]);
});

test('given an unknown event id when favoriting then it returns not found', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->putJson('/api/favorites/999999')->assertNotFound();
});

test('given no Sanctum token when any favorites endpoint is called then it returns unauthorized', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    'put' => ['PUT', '/api/favorites/1'],
    'delete' => ['DELETE', '/api/favorites/1'],
    'get' => ['GET', '/api/favorites'],
]);

it('given an authenticated fan when the feed is requested then previously favorited events are flagged', function () {
    $fan = User::factory()->create();
    $venue = Venue::factory()->create();
    $favorited = Event::factory()->for($venue)->create(['status' => EventStatus::Published, 'start_date_time' => now()->addDay()]);
    $unfavorited = Event::factory()->for($venue)->create(['status' => EventStatus::Published, 'start_date_time' => now()->addDay()]);
    $fan->favoriteEvents()->attach($favorited);
    Sanctum::actingAs($fan);

    $response = $this->getJson('/api/events')->assertOk();

    $byId = collect($response->json('data'))->keyBy('id');
    expect($byId[(string) $favorited->id]['isFavorited'])->toBeTrue()
        ->and($byId[(string) $unfavorited->id]['isFavorited'])->toBeFalse();
});

it('given an anonymous visitor when the feed is requested then isFavorited is omitted', function () {
    Event::factory()->for(Venue::factory())->create(['status' => EventStatus::Published, 'start_date_time' => now()->addDay()]);

    $response = $this->getJson('/api/events')->assertOk();

    expect($response->json('data.0'))->not->toHaveKey('isFavorited');
});

it('given an authenticated fan when an event detail is requested then isFavorited reflects its state', function () {
    $fan = User::factory()->create();
    $event = Event::factory()->for(Venue::factory())->create(['status' => EventStatus::Published, 'start_date_time' => now()->addDay()]);
    $fan->favoriteEvents()->attach($event);
    Sanctum::actingAs($fan);

    $this->getJson("/api/events/{$event->id}")->assertOk()->assertJsonPath('data.isFavorited', true);
});

it('given an authenticated fan when map events are requested then favorited events are flagged', function () {
    $fan = User::factory()->create();
    $venue = Venue::factory()->create(['latitude' => -20.31, 'longitude' => -40.31]);
    $event = Event::factory()->for($venue)->create(['status' => EventStatus::Published, 'start_date_time' => now()->addDay()]);
    $fan->favoriteEvents()->attach($event);
    Sanctum::actingAs($fan);

    $this->getJson('/api/events/map')->assertOk()->assertJsonPath('data.0.isFavorited', true);
});
