<?php

use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;

it('given repeated syncWithoutDetaching calls when favoriting then exactly one pivot row exists', function () {
    $fan = User::factory()->create();
    $event = Event::factory()->for(Venue::factory())->create();

    $fan->favoriteEvents()->syncWithoutDetaching([$event->id]);
    $fan->favoriteEvents()->syncWithoutDetaching([$event->id]);

    expect(DB::table('favorites')->where('user_id', $fan->id)->where('event_id', $event->id)->count())->toBe(1);
});

it('given no existing favorite when detach is called then it is a no-op, not an error', function () {
    $fan = User::factory()->create();
    $event = Event::factory()->for(Venue::factory())->create();

    $fan->favoriteEvents()->detach($event->id);

    expect(DB::table('favorites')->where('user_id', $fan->id)->count())->toBe(0);
});

it('given a favorited event when the user is deleted then the pivot row cascades', function () {
    $fan = User::factory()->create();
    $event = Event::factory()->for(Venue::factory())->create();
    $fan->favoriteEvents()->attach($event);

    $fan->delete();

    expect(DB::table('favorites')->where('event_id', $event->id)->exists())->toBeFalse();
});

it('given a favorited event when the event is deleted then the pivot row cascades', function () {
    $fan = User::factory()->create();
    $event = Event::factory()->for(Venue::factory())->create();
    $fan->favoriteEvents()->attach($event);

    $event->delete();

    expect(DB::table('favorites')->where('user_id', $fan->id)->exists())->toBeFalse();
});

it('given no Sanctum token when the favorites endpoints are called then the real guard rejects them', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    'put' => ['PUT', '/api/favorites/1'],
    'delete' => ['DELETE', '/api/favorites/1'],
    'get' => ['GET', '/api/favorites'],
]);
