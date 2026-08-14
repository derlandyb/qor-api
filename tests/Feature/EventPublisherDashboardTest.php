<?php

use App\Enums\EventStatus;
use App\Enums\VerificationStatus;
use App\Models\Event;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use Laravel\Sanctum\Sanctum;

it('given a promoter with mixed status events when the dashboard is listed then only their own events with correct statuses are returned', function () {
    $user = User::factory()->create();
    $promoter = Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $mine = Event::factory()->create(['status' => EventStatus::Draft]);
    $mine->promoters()->attach($promoter);
    $notMine = Event::factory()->create(['status' => EventStatus::Draft]);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/admin/publisher/events')->assertOk();

    $ids = collect($response->json('data'))->pluck('event.id')->all();
    expect($ids)->toBe([(string) $mine->id])
        ->and($response->json('data.0.dashboardStatus.label'))->toBe('draft');
});

it('given a status query param when the dashboard is listed then only matching rows are returned', function () {
    $user = User::factory()->create();
    $promoter = Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $draft = Event::factory()->create(['status' => EventStatus::Draft]);
    $draft->promoters()->attach($promoter);
    $pending = Event::factory()->create(['status' => EventStatus::PendingApproval]);
    $pending->promoters()->attach($promoter);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/admin/publisher/events?status=draft')->assertOk();

    $ids = collect($response->json('data'))->pluck('event.id')->all();
    expect($ids)->toBe([(string) $draft->id]);
});

it('given a venue account when the dashboard is listed then events submitted by other promoters at that venue carry promoterNames', function () {
    $promoter = Promoter::factory()->create(['name' => 'Rock in Rio Produções']);
    $venueUser = User::factory()->create();
    $venue = Venue::factory()->create(['user_id' => $venueUser->id, 'verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create(['status' => EventStatus::Draft]);
    $event->promoters()->attach($promoter);
    Sanctum::actingAs($venueUser);

    $response = $this->getJson('/api/admin/publisher/events')->assertOk();

    expect($response->json('data.0.event.promoterNames'))->toBe(['Rock in Rio Produções']);
});

test('given no Sanctum token when the publisher dashboard endpoint is called then it returns unauthorized', function () {
    $this->getJson('/api/admin/publisher/events')->assertUnauthorized();
});
