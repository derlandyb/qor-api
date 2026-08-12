<?php

use App\Enums\EventRevisionStatus;
use App\Models\Event;
use App\Models\EventRevision;
use App\Models\Venue;

it('given a published event with no existing revision when an edit is submitted then a new revision row is created', function () {
    $event = Event::factory()->for(Venue::factory())->create();
    $venue = Venue::factory()->create();

    $revision = EventRevision::submitFor($event, [
        'title' => 'Novo Título',
        'start_date_time' => now()->addDay(),
        'venue_id' => $venue->id,
    ]);

    expect(EventRevision::query()->count())->toBe(1)
        ->and($revision->event_id)->toBe($event->id)
        ->and($revision->title)->toBe('Novo Título')
        ->and($revision->status)->toBe(EventRevisionStatus::PendingApproval);
});

it('given a published event with an already pending revision when a second edit is submitted then it replaces the first rather than creating a competing row', function () {
    $event = Event::factory()->for(Venue::factory())->create();
    $venue = Venue::factory()->create();
    $first = EventRevision::factory()->for($event)->create(['title' => 'Primeira Edição']);

    $second = EventRevision::submitFor($event, [
        'title' => 'Segunda Edição',
        'start_date_time' => now()->addDay(),
        'venue_id' => $venue->id,
    ]);

    expect(EventRevision::query()->count())->toBe(1)
        ->and($second->id)->toBe($first->id)
        ->and($second->fresh()->title)->toBe('Segunda Edição');
});

it('given a rejected revision when it is resubmitted then reviewer feedback is cleared and status returns to pending approval', function () {
    $event = Event::factory()->for(Venue::factory())->create();
    $venue = Venue::factory()->create();
    EventRevision::factory()->for($event)->create([
        'status' => EventRevisionStatus::ChangesRequested,
        'reviewer_feedback' => 'Falta a data de término.',
    ]);

    $revision = EventRevision::submitFor($event, [
        'title' => 'Edição Corrigida',
        'start_date_time' => now()->addDay(),
        'venue_id' => $venue->id,
    ]);

    expect($revision->status)->toBe(EventRevisionStatus::PendingApproval)
        ->and($revision->reviewer_feedback)->toBeNull();
});
