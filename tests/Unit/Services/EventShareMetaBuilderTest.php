<?php

use App\Enums\BannerStatus;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Venue;
use App\Services\EventShareMetaBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

it('given a published upcoming event when share metadata is built then the title has no status suffix and no banner status', function () {
    $venue = Venue::factory()->state(['name' => 'Arena Fonte Nova', 'city' => 'Vitória']);
    $event = Event::factory()->for($venue)->make([
        'title' => 'Show de Rock',
        'status' => EventStatus::Published,
        'start_date_time' => now()->addWeek(),
        'city' => 'Vitória',
    ]);

    $meta = EventShareMetaBuilder::build($event);

    expect($meta->title)->toBe('Show de Rock');
    expect($meta->bannerStatus)->toBeNull();
    expect($meta->description)->toContain('Arena Fonte Nova, Vitória');
});

it('given a cancelled event when share metadata is built then the title is suffixed and the description is unaffected', function () {
    $venue = Venue::factory()->state(['name' => 'Arena Fonte Nova', 'city' => 'Vitória']);
    $event = Event::factory()->for($venue)->make([
        'title' => 'Show de Rock',
        'status' => EventStatus::Cancelled,
        'start_date_time' => now()->addWeek(),
        'city' => 'Vitória',
    ]);

    $meta = EventShareMetaBuilder::build($event);

    expect($meta->title)->toBe('Show de Rock (Evento cancelado)');
    expect($meta->bannerStatus)->toBe(BannerStatus::Cancelled);
    expect($meta->description)->toContain('Arena Fonte Nova, Vitória');
});

it('given a finished event when share metadata is built then the title is suffixed', function () {
    $venue = Venue::factory()->state(['name' => 'Arena Fonte Nova', 'city' => 'Vitória']);
    $event = Event::factory()->for($venue)->make([
        'title' => 'Show de Rock',
        'status' => EventStatus::Published,
        'start_date_time' => now()->subWeek(),
        'city' => 'Vitória',
    ]);

    $meta = EventShareMetaBuilder::build($event);

    expect($meta->title)->toBe('Show de Rock (Este evento já aconteceu)');
    expect($meta->bannerStatus)->toBe(BannerStatus::Finished);
});

it('given an event with a cover image when share metadata is built then the image url is included', function () {
    Storage::fake('s3');
    $venue = Venue::factory()->state(['city' => 'Vitória']);
    $event = Event::factory()->for($venue)->make([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addWeek(),
        'cover_image_path' => 'events/covers/cover.jpg',
        'city' => 'Vitória',
    ]);

    $meta = EventShareMetaBuilder::build($event);

    expect($meta->imageUrl)->toBe(Storage::disk('s3')->url('events/covers/cover.jpg'));
});

it('given an event with no cover image when share metadata is built then the image url is absent, not an empty string', function () {
    $venue = Venue::factory()->state(['city' => 'Vitória']);
    $event = Event::factory()->for($venue)->make([
        'status' => EventStatus::Published,
        'start_date_time' => now()->addWeek(),
        'cover_image_path' => null,
        'city' => 'Vitória',
    ]);

    $meta = EventShareMetaBuilder::build($event);

    expect($meta->imageUrl)->toBeNull();
});

it('given an event when share metadata is built then the description includes a formatted date', function () {
    $venue = Venue::factory()->state(['name' => 'Arena Fonte Nova', 'city' => 'Vitória']);
    $event = Event::factory()->for($venue)->make([
        'status' => EventStatus::Published,
        'start_date_time' => Carbon::create(2026, 8, 15, 20, 30, 0),
        'city' => 'Vitória',
    ]);

    $meta = EventShareMetaBuilder::build($event);

    expect($meta->description)->toMatch('/\d{2}:\d{2}/');
});
