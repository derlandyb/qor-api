<?php

use App\Models\Event;
use App\Models\Venue;

it('given an event assigned to a venue when it is saved then its city is denormalized from the venue', function () {
    $venue = Venue::factory()->create(['city' => 'Vitória']);

    $event = Event::factory()->for($venue)->create();

    expect($event->city)->toBe('Vitória');
});

it('given an event moved to a different venue when it is saved then its city is updated to the new venue city', function () {
    $originalVenue = Venue::factory()->create(['city' => 'Vitória']);
    $newVenue = Venue::factory()->create(['city' => 'Serra']);
    $event = Event::factory()->for($originalVenue)->create();

    $event->update(['venue_id' => $newVenue->id]);

    expect($event->fresh()->city)->toBe('Serra');
});
