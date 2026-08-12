<?php

use App\Enums\VerificationApplicationStatus;
use App\Models\User;
use App\Models\VerificationApplication;
use Laravel\Sanctum\Sanctum;

it('given a verification application backdated past 5 business days crossing a real weekend when the queue is fetched then it is stale', function () {
    // A Monday 8 calendar days ago crosses exactly one real weekend before "now" — more than
    // 5 business days ago regardless of which weekday "now" happens to fall on in CI.
    VerificationApplication::factory()->create([
        'status' => VerificationApplicationStatus::PendingReview,
        'created_at' => now()->subDays(8),
    ]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->getJson('/api/admin/moderation/verification-queue')->assertOk();

    expect($response->json('data.0.staleness.isStale'))->toBeTrue()
        ->and($response->json('queueStaleness.isStale'))->toBeTrue();
});
