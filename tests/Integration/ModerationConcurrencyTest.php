<?php

use App\Enums\EventStatus;
use App\Enums\VerificationApplicationStatus;
use App\Enums\VerificationStatus;
use App\Models\Event;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use App\Models\VerificationApplication;
use Laravel\Sanctum\Sanctum;

it('given two admins approving the same verification application when the second request lands after the first commits then only one succeeds', function () {
    $applicant = User::factory()->create();
    $promoter = Promoter::factory()->create(['user_id' => $applicant->id, 'verification_status' => VerificationStatus::PendingReview]);
    $application = VerificationApplication::factory()->create([
        'user_id' => $applicant->id,
        'promoter_id' => $promoter->id,
        'venue_id' => null,
        'status' => VerificationApplicationStatus::PendingReview,
    ]);
    Sanctum::actingAs(User::factory()->admin()->create());

    // Sequential in-test (the first request's transaction commits before the second is issued) —
    // deterministically exercises the lock-then-guard path rather than relying on real thread races.
    $first = $this->postJson("/api/admin/verification-applications/{$application->id}/approve");
    $second = $this->postJson("/api/admin/verification-applications/{$application->id}/approve");

    $first->assertOk();
    $second->assertStatus(409);
    expect($application->fresh()->status)->toBe(VerificationApplicationStatus::Verified);
});

it('given two admins approving the same event when the second request lands after the first commits then only one succeeds', function () {
    $venue = Venue::factory()->create(['verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create(['status' => EventStatus::PendingApproval]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $first = $this->postJson("/api/admin/events/{$event->id}/approve");
    $second = $this->postJson("/api/admin/events/{$event->id}/approve");

    $first->assertOk();
    $second->assertStatus(409);
    expect($event->fresh()->status)->toBe(EventStatus::Published);
});
