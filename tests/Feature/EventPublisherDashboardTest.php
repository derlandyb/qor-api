<?php

use App\Enums\EventStatus;
use App\Enums\VerificationStatus;
use App\Models\Event;
use App\Models\Promoter;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('given a promoter with mixed status events when the dashboard is listed then only their own events with correct statuses are returned', function () {
    $user = User::factory()->create();
    $promoter = Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $mine = Event::factory()->create(['status' => EventStatus::Draft]);
    $mine->promoters()->attach($promoter);
    $notMine = Event::factory()->create(['status' => EventStatus::Draft]);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/publisher/events')->assertOk();

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

    $response = $this->getJson('/api/publisher/events?status=draft')->assertOk();

    $ids = collect($response->json('data'))->pluck('event.id')->all();
    expect($ids)->toBe([(string) $draft->id]);
});

test('given no Sanctum token when the publisher dashboard endpoint is called then it returns unauthorized', function () {
    $this->getJson('/api/publisher/events')->assertUnauthorized();
});
