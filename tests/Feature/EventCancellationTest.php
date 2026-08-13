<?php

use App\Enums\EventStatus;
use App\Enums\VerificationStatus;
use App\Models\Event;
use App\Models\EventRevision;
use App\Models\User;
use App\Models\Venue;
use Laravel\Sanctum\Sanctum;

it('given a publisher when cancelling an approved event then it is immediately cancelled and remains resolvable publicly', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create(['status' => EventStatus::Published, 'start_date_time' => now()->addWeek()]);
    Sanctum::actingAs($user);

    $this->postJson("/api/admin/publisher/events/{$event->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($event->fresh()->status)->toBe(EventStatus::Cancelled);

    // Immediately resolvable publicly, per PUBLISH-005/SHARE-003 — cancelled events stay
    // visible (not deleted/hidden) via the public feed and detail endpoints without delay.
    $this->getJson('/api/events')
        ->assertOk()
        ->assertJsonPath('data.0.id', (string) $event->id)
        ->assertJsonPath('data.0.status', 'cancelled');

    $this->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.bannerStatus', 'cancelled');
});

it('given an already cancelled event when it is cancelled again then it conflicts', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create(['status' => EventStatus::Cancelled]);
    Sanctum::actingAs($user);

    $this->postJson("/api/admin/publisher/events/{$event->id}/cancel")->assertStatus(409);
});

it('given a published event with a pending edit when it is cancelled then the edit is discarded atomically', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create(['status' => EventStatus::Published]);
    EventRevision::factory()->for($event)->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/admin/publisher/events/{$event->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($event->fresh()->status)->toBe(EventStatus::Cancelled)
        ->and(EventRevision::query()->where('event_id', $event->id)->count())->toBe(0);

    $dashboard = $this->getJson('/api/admin/publisher/events')->assertOk();
    expect($dashboard->json('data.0.dashboardStatus.hasPendingEdit'))->toBeFalse();
});

it('given an event owned by another publisher when it is cancelled then it is forbidden', function () {
    $owner = Venue::factory()->create(['verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($owner)->create(['status' => EventStatus::Published]);

    $intruder = User::factory()->create();
    Venue::factory()->create(['user_id' => $intruder->id, 'verification_status' => VerificationStatus::Verified]);
    Sanctum::actingAs($intruder);

    $this->postJson("/api/admin/publisher/events/{$event->id}/cancel")->assertForbidden();

    expect($event->fresh()->status)->toBe(EventStatus::Published);
});
