<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Venue;

it('given mixed event statuses when the upcoming scope runs then it returns only upcoming published events soonest first', function () {
    $venue = Venue::factory()->create();

    $pastPublished = Event::factory()->for($venue)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->subDay(),
    ]);
    $draftUpcoming = Event::factory()->for($venue)->create([
        'status' => EventStatus::Draft,
        'start_date_time' => now()->addDay(),
    ]);
    $sooner = Event::factory()->for($venue)->create([
        'status' => EventStatus::Cancelled,
        'start_date_time' => now()->addDay(),
    ]);
    $later = Event::factory()->for($venue)->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDays(3),
    ]);

    $result = Event::query()->publishedUpcoming()->orderBy('start_date_time')->get();

    expect($result->pluck('id')->all())->toBe([$sooner->id, $later->id])
        ->and($result->pluck('id'))->not->toContain($pastPublished->id, $draftUpcoming->id);
});
