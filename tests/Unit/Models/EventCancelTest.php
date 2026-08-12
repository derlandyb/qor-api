<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventRevision;
use App\Models\Venue;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('given a published event when it is cancelled then its status becomes cancelled', function () {
    $event = Event::factory()->for(Venue::factory())->create(['status' => EventStatus::Published]);

    $event->cancel();

    expect($event->status)->toBe(EventStatus::Cancelled)
        ->and($event->fresh()->status)->toBe(EventStatus::Cancelled);
});

it('given an already cancelled event when it is cancelled again then it throws a 409', function () {
    $event = Event::factory()->for(Venue::factory())->create(['status' => EventStatus::Cancelled]);

    expect(fn () => $event->cancel())->toThrow(HttpException::class);

    try {
        $event->cancel();
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(409);
    }
});

it('given every non cancelled status when the event is cancelled then it always succeeds', function (EventStatus $status) {
    $event = Event::factory()->for(Venue::factory())->create(['status' => $status]);

    $event->cancel();

    expect($event->status)->toBe(EventStatus::Cancelled);
})->with([
    EventStatus::Draft,
    EventStatus::PendingApproval,
    EventStatus::Approved,
    EventStatus::Published,
    EventStatus::ChangesRequested,
    EventStatus::Finished,
]);

it('given a published event with a pending revision when it is cancelled then the revision is discarded in the same operation', function () {
    $event = Event::factory()->for(Venue::factory())->create(['status' => EventStatus::Published]);
    EventRevision::factory()->for($event)->create();

    $event->cancel();

    expect($event->status)->toBe(EventStatus::Cancelled)
        ->and($event->pendingRevision()->exists())->toBeFalse()
        ->and(EventRevision::query()->where('event_id', $event->id)->exists())->toBeFalse();
});

it('given a published event with no pending revision when it is cancelled then the discard is a no-op', function () {
    $event = Event::factory()->for(Venue::factory())->create(['status' => EventStatus::Published]);

    $event->cancel();

    expect($event->status)->toBe(EventStatus::Cancelled)
        ->and($event->pendingRevision()->exists())->toBeFalse();
});
