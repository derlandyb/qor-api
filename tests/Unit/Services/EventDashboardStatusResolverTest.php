<?php

use App\Enums\EventRevisionStatus;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventRevision;
use App\Models\Venue;
use App\Services\EventDashboardStatusResolver;

it('given a first-time submission status when the dashboard status is resolved then the label and feedback come directly from the event', function (EventStatus $status) {
    $event = Event::factory()->for(Venue::factory())->create([
        'status' => $status,
        'reviewer_feedback' => 'Falta a imagem de capa.',
    ]);

    $view = EventDashboardStatusResolver::resolve($event);

    expect($view->label)->toBe($status)
        ->and($view->reviewerFeedback)->toBe('Falta a imagem de capa.')
        ->and($view->hasPendingEdit)->toBeFalse()
        ->and($view->pendingEditStatus)->toBeNull();
})->with([
    EventStatus::Draft,
    EventStatus::PendingApproval,
    EventStatus::ChangesRequested,
]);

it('given a published event with a future date and no revision when the dashboard status is resolved then it returns published with no pending edit', function () {
    $event = Event::factory()->for(Venue::factory())->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDay(),
        'end_date_time' => null,
    ]);

    $view = EventDashboardStatusResolver::resolve($event);

    expect($view->label)->toBe(EventStatus::Published)
        ->and($view->hasPendingEdit)->toBeFalse();
});

it('given a published event with a pending revision when the dashboard status is resolved then it returns published with the pending edit surfaced', function () {
    $event = Event::factory()->for(Venue::factory())->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDay(),
        'end_date_time' => null,
    ]);
    EventRevision::factory()->for($event)->create(['status' => EventRevisionStatus::PendingApproval, 'reviewer_feedback' => null]);

    $view = EventDashboardStatusResolver::resolve($event->fresh());

    expect($view->label)->toBe(EventStatus::Published)
        ->and($view->hasPendingEdit)->toBeTrue()
        ->and($view->pendingEditStatus)->toBe(EventRevisionStatus::PendingApproval);
});

it('given a published event whose date has passed when the dashboard status is resolved then it delegates to EventBannerStatusResolver and returns finished', function () {
    $event = Event::factory()->for(Venue::factory())->create([
        'status' => EventStatus::Published,
        'start_date_time' => now()->subWeek(),
        'end_date_time' => null,
    ]);

    $view = EventDashboardStatusResolver::resolve($event);

    expect($view->label)->toBe(EventStatus::Finished);
});

it('given a cancelled event with a past date when the dashboard status is resolved then cancelled wins over finished', function () {
    $event = Event::factory()->for(Venue::factory())->create([
        'status' => EventStatus::Cancelled,
        'start_date_time' => now()->subWeek(),
        'end_date_time' => null,
    ]);

    $view = EventDashboardStatusResolver::resolve($event);

    expect($view->label)->toBe(EventStatus::Cancelled);
});

it('given a cancelled event that had a pending revision when the dashboard status is resolved then no pending edit is surfaced since cancel discards it', function () {
    $event = Event::factory()->for(Venue::factory())->create([
        'status' => EventStatus::Cancelled,
        'start_date_time' => now()->addDay(),
    ]);
    // No EventRevision row exists — cancel() already discarded it (PUBLISH-007); this resolver
    // just proves it correctly reflects "none" rather than re-deriving cancellation's own effect.

    $view = EventDashboardStatusResolver::resolve($event);

    expect($view->label)->toBe(EventStatus::Cancelled)
        ->and($view->hasPendingEdit)->toBeFalse();
});
