<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Venue;

it('given more events than one page when the feed is walked by cursor then every event appears exactly once', function () {
    $venue = Venue::factory()->create();
    Event::factory()->for($venue)->count(45)->sequence(
        fn ($sequence) => ['start_date_time' => now()->addMinutes($sequence->index + 1)],
    )->create(['status' => EventStatus::Published]);

    $seenIds = [];
    $cursor = null;

    do {
        $page = Event::query()
            ->publishedUpcoming()
            ->orderBy('start_date_time')->orderBy('title')->orderBy('id')
            ->cursorPaginate(20, ['*'], 'cursor', $cursor);

        foreach ($page->items() as $event) {
            $seenIds[] = $event->id;
        }

        $cursor = $page->nextCursor();
    } while ($cursor !== null);

    expect($seenIds)->toHaveCount(45)
        ->and(array_unique($seenIds))->toHaveCount(45);
});
