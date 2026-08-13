<?php

use App\Enums\VerificationStatus;
use App\Models\Promoter;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

// admin-auth (ADMIN-AUTH-006) — event-publishing's publisher routes moved under /api/admin/publisher/*;
// the old /api/publisher/* prefix must no longer resolve to anything.
it('given the old /api/publisher/* paths when called then they 404', function () {
    $user = User::factory()->create();
    Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    Sanctum::actingAs($user);

    $this->getJson('/api/publisher/events')->assertNotFound();
    $this->postJson('/api/publisher/events', [])->assertNotFound();
    $this->getJson('/api/publisher/venues')->assertNotFound();
});
