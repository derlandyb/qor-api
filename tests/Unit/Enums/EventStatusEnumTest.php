<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Venue;

it('given an event status when it is persisted and reloaded then it round-trips through the enum cast', function (EventStatus $status) {
    $venue = Venue::factory()->create();
    $event = Event::factory()->for($venue)->create(['status' => $status]);

    expect($event->fresh()->status)->toBe($status);
})->with(EventStatus::cases());
