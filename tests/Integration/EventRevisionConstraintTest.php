<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventRevision;
use App\Models\Venue;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('given an event with an existing pending revision when a second revision row is inserted directly then the database rejects it', function () {
    $venue = Venue::factory()->create();
    $event = Event::factory()->for($venue)->create(['status' => EventStatus::Published]);
    EventRevision::factory()->for($event)->create();

    expect(fn () => DB::table('event_revisions')->insert([
        'event_id' => $event->id,
        'title' => 'Segunda linha concorrente',
        'start_date_time' => now()->addDay(),
        'venue_id' => $venue->id,
        'status' => 'pending_approval',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
