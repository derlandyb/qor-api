<?php

use App\Enums\VerificationStatus;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use Laravel\Sanctum\Sanctum;

it('given a venue account when listing venue options then only its own venue is returned with accountType venue', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    Venue::factory()->create(['verification_status' => VerificationStatus::Verified]); // another verified venue, not owned
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/admin/publisher/venues')->assertOk();

    expect($response->json('accountType'))->toBe('venue')
        ->and($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe((string) $venue->id);
});

it('given a promoter account when listing venue options then all verified venues are returned with accountType promoter', function () {
    $user = User::factory()->create();
    Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    Venue::factory()->count(3)->create(['verification_status' => VerificationStatus::Verified]);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/admin/publisher/venues')->assertOk();

    expect($response->json('accountType'))->toBe('promoter')
        ->and($response->json('data'))->toHaveCount(3);
});

it('given a promoter account when an unverified venue exists then it is excluded from the list', function () {
    $user = User::factory()->create();
    Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    Venue::factory()->create(['verification_status' => VerificationStatus::Verified]);
    Venue::factory()->create(['verification_status' => VerificationStatus::Unverified]);
    Venue::factory()->create(['verification_status' => VerificationStatus::PendingReview]);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/admin/publisher/venues')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

it('given an unverified promoter account when listing venue options then it is forbidden', function () {
    $user = User::factory()->create();
    Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Unverified]);
    Sanctum::actingAs($user);

    $this->getJson('/api/admin/publisher/venues')->assertForbidden();
});

it('given a promoter account when no verified venues exist then an empty list is returned', function () {
    $user = User::factory()->create();
    Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/admin/publisher/venues')->assertOk();

    expect($response->json('data'))->toBe([]);
});

it('given a promoter account when listing venue options then results are ordered by name', function () {
    $user = User::factory()->create();
    Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    Venue::factory()->create(['name' => 'Zeta Casa de Shows', 'verification_status' => VerificationStatus::Verified]);
    Venue::factory()->create(['name' => 'Alpha Casa de Shows', 'verification_status' => VerificationStatus::Verified]);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/admin/publisher/venues')->assertOk();

    expect($response->json('data.*.name'))->toBe(['Alpha Casa de Shows', 'Zeta Casa de Shows']);
});

test('given no Sanctum token when the venue options endpoint is called then it returns unauthorized', function () {
    $this->getJson('/api/admin/publisher/venues')->assertUnauthorized();
});
