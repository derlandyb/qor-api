<?php

use App\Enums\BannerStatus;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Venue;
use App\Services\EventBannerStatusResolver;

it('given a cancelled event with a future date when the banner status is resolved then it returns cancelled', function () {
    $event = Event::factory()->for(Venue::factory())->make([
        'status' => EventStatus::Cancelled,
        'start_date_time' => now()->addDay(),
        'end_date_time' => null,
    ]);

    expect(EventBannerStatusResolver::resolve($event))->toBe(BannerStatus::Cancelled);
});

it('given a cancelled event with a past date when the banner status is resolved then cancelled still wins over finished', function () {
    $event = Event::factory()->for(Venue::factory())->make([
        'status' => EventStatus::Cancelled,
        'start_date_time' => now()->subWeek(),
        'end_date_time' => null,
    ]);

    expect(EventBannerStatusResolver::resolve($event))->toBe(BannerStatus::Cancelled);
});

it('given a published event with a future date when the banner status is resolved then it returns no banner', function () {
    $event = Event::factory()->for(Venue::factory())->make([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addDay(),
        'end_date_time' => null,
    ]);

    expect(EventBannerStatusResolver::resolve($event))->toBeNull();
});

it('given a cancelled event with a future date when isFinished is checked directly then it returns false', function () {
    $event = Event::factory()->for(Venue::factory())->make([
        'status' => EventStatus::Cancelled,
        'start_date_time' => now()->addDay(),
        'end_date_time' => null,
    ]);

    // isFinished() intentionally ignores the Cancelled/Finished precedence rule that resolve()
    // applies — it answers only "is this event's date/time in the past," the exact predicate
    // favorites' Upcoming/Passados split needs (a future-dated cancelled event stays Upcoming).
    expect(EventBannerStatusResolver::isFinished($event))->toBeFalse();
});

it('given a published event whose date has passed when the banner status is resolved then it derives finished from the clock', function () {
    $event = Event::factory()->for(Venue::factory())->make([
        'status' => EventStatus::Published,
        'start_date_time' => now()->subDay(),
        'end_date_time' => null,
    ]);

    expect(EventBannerStatusResolver::resolve($event))->toBe(BannerStatus::Finished);
});

it('given a persisted finished event when the banner status is resolved then it returns finished regardless of date', function () {
    $event = Event::factory()->for(Venue::factory())->make([
        'status' => EventStatus::Finished,
        'start_date_time' => now()->addDay(),
        'end_date_time' => null,
    ]);

    expect(EventBannerStatusResolver::resolve($event))->toBe(BannerStatus::Finished);
});

it('given a published event with an end date when the banner status is resolved then it uses the end date over the start date', function () {
    $event = Event::factory()->for(Venue::factory())->make([
        'status' => EventStatus::Published,
        'start_date_time' => now()->subWeek(),
        'end_date_time' => now()->addDay(),
    ]);

    expect(EventBannerStatusResolver::resolve($event))->toBeNull();
});
