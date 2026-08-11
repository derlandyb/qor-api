<?php

use App\Models\Artist;
use App\Models\Event;
use App\Models\Promoter;
use App\Models\Venue;

it('given a venue with events when its events relation is accessed then it returns those events', function () {
    $venue = Venue::factory()->create();
    $event = Event::factory()->for($venue)->create();

    expect($venue->events()->pluck('id')->all())->toBe([$event->id]);
});

it('given an event with attached artists and promoters when accessed then the relations resolve', function () {
    $venue = Venue::factory()->create();
    $event = Event::factory()->for($venue)->create();
    $artist = Artist::factory()->create();
    $promoter = Promoter::factory()->create();

    $event->artists()->attach($artist);
    $event->promoters()->attach($promoter);

    expect($event->artists()->pluck('artists.id')->all())->toBe([$artist->id])
        ->and($event->promoters()->pluck('promoters.id')->all())->toBe([$promoter->id])
        ->and($artist->events()->pluck('events.id')->all())->toBe([$event->id])
        ->and($promoter->events()->pluck('events.id')->all())->toBe([$event->id]);
});
