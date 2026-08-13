<?php

use App\Enums\VerificationStatus;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use Laravel\Sanctum\Sanctum;

it('given a moderator when revoking a promoter with a reason then it is unverified and the reason is persisted', function () {
    $promoter = Promoter::factory()->create(['verification_status' => VerificationStatus::Verified]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson("/api/admin/promoters/{$promoter->id}/revoke", ['reason' => 'Reclamações de clientes.'])
        ->assertOk()
        ->assertJsonPath('data.verificationStatus', 'unverified');

    expect($promoter->fresh()->verification_status)->toBe(VerificationStatus::Unverified)
        ->and($promoter->fresh()->revocation_reason)->toBe('Reclamações de clientes.');
});

it('given a blank reason when a promoter revocation is requested then it returns a field level error', function () {
    $promoter = Promoter::factory()->create(['verification_status' => VerificationStatus::Verified]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson("/api/admin/promoters/{$promoter->id}/revoke", ['reason' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');

    expect($promoter->fresh()->verification_status)->toBe(VerificationStatus::Verified);
});

it('given a missing reason when a promoter revocation is requested then it returns a field level error', function () {
    $promoter = Promoter::factory()->create(['verification_status' => VerificationStatus::Verified]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson("/api/admin/promoters/{$promoter->id}/revoke")
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');

    expect($promoter->fresh()->verification_status)->toBe(VerificationStatus::Verified);
});

it('given a moderator when revoking a venue with a reason then it is unverified and the reason is persisted', function () {
    $venue = Venue::factory()->create(['verification_status' => VerificationStatus::Verified]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson("/api/admin/venues/{$venue->id}/revoke", ['reason' => 'Endereço não confere.'])
        ->assertOk()
        ->assertJsonPath('data.verificationStatus', 'unverified');

    expect($venue->fresh()->verification_status)->toBe(VerificationStatus::Unverified)
        ->and($venue->fresh()->revocation_reason)->toBe('Endereço não confere.');
});

it('given a blank reason when a venue revocation is requested then it returns a field level error', function () {
    $venue = Venue::factory()->create(['verification_status' => VerificationStatus::Verified]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson("/api/admin/venues/{$venue->id}/revoke", ['reason' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');

    expect($venue->fresh()->verification_status)->toBe(VerificationStatus::Verified);
});
