<?php

use App\Enums\VerificationApplicationStatus;
use App\Enums\VerificationStatus;
use App\Models\Promoter;
use App\Models\User;
use App\Models\VerificationApplication;
use Laravel\Sanctum\Sanctum;

it('given a rejected application when the applicant checks status then feedback is visible and reapplication is allowed', function () {
    $applicant = User::factory()->create();
    $promoter = Promoter::factory()->create(['user_id' => $applicant->id, 'verification_status' => VerificationStatus::PendingReview]);
    $application = VerificationApplication::factory()->create([
        'user_id' => $applicant->id,
        'promoter_id' => $promoter->id,
        'venue_id' => null,
        'status' => VerificationApplicationStatus::PendingReview,
    ]);
    $admin = User::factory()->admin()->create();
    Sanctum::actingAs($admin);

    $this->postJson("/api/admin/verification-applications/{$application->id}/reject", ['feedback' => 'Falta comprovante de endereço.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    Sanctum::actingAs($applicant);
    $this->getJson('/api/verification/status')
        ->assertOk()
        ->assertJsonPath('status', 'unverified')
        ->assertJsonPath('rejectionFeedback', 'Falta comprovante de endereço.');

    // No cooldown — a fresh submission right after a rejection must succeed.
    $this->postJson('/api/verification/applications', [
        'business_name' => 'Nova Produtora',
        'account_type' => 'promoter',
        'contact_email' => 'contato@novaprodutora.example',
        'social_link' => 'https://wa.me/5527999998888',
    ])->assertCreated();
});

it('given an already actioned application when it is approved again then it conflicts', function () {
    $applicant = User::factory()->create();
    $promoter = Promoter::factory()->create(['user_id' => $applicant->id]);
    $application = VerificationApplication::factory()->create([
        'user_id' => $applicant->id,
        'promoter_id' => $promoter->id,
        'venue_id' => null,
        'status' => VerificationApplicationStatus::PendingReview,
    ]);
    $admin = User::factory()->admin()->create();
    Sanctum::actingAs($admin);
    $this->postJson("/api/admin/verification-applications/{$application->id}/approve")->assertOk();

    $this->postJson("/api/admin/verification-applications/{$application->id}/approve")->assertStatus(409);
    $this->postJson("/api/admin/verification-applications/{$application->id}/reject", ['feedback' => 'too late'])->assertStatus(409);
});

it('given a blank feedback when an application is rejected then it returns a field level error', function () {
    $applicant = User::factory()->create();
    $promoter = Promoter::factory()->create(['user_id' => $applicant->id]);
    $application = VerificationApplication::factory()->create([
        'user_id' => $applicant->id,
        'promoter_id' => $promoter->id,
        'venue_id' => null,
        'status' => VerificationApplicationStatus::PendingReview,
    ]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson("/api/admin/verification-applications/{$application->id}/reject", ['feedback' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('feedback');
});

it('given a pending application when it is approved then the promoter becomes verified', function () {
    $applicant = User::factory()->create();
    $promoter = Promoter::factory()->create(['user_id' => $applicant->id, 'verification_status' => VerificationStatus::PendingReview]);
    $application = VerificationApplication::factory()->create([
        'user_id' => $applicant->id,
        'promoter_id' => $promoter->id,
        'venue_id' => null,
        'status' => VerificationApplicationStatus::PendingReview,
    ]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson("/api/admin/verification-applications/{$application->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'verified');

    expect($promoter->fresh()->verification_status)->toBe(VerificationStatus::Verified);
});

test('given applications with different statuses when the admin index is filtered then only matching ones are returned', function () {
    $pending = VerificationApplication::factory()->create(['status' => VerificationApplicationStatus::PendingReview]);
    VerificationApplication::factory()->create(['status' => VerificationApplicationStatus::Verified]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->getJson('/api/admin/verification-applications?status=pending_review')->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([(string) $pending->id]);
});

it('given a non-admin account when any moderation admin route is called then it is forbidden', function () {
    $applicant = User::factory()->create();
    $promoter = Promoter::factory()->create(['user_id' => $applicant->id]);
    $application = VerificationApplication::factory()->create([
        'user_id' => $applicant->id,
        'promoter_id' => $promoter->id,
        'venue_id' => null,
        'status' => VerificationApplicationStatus::PendingReview,
    ]);
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/admin/verification-applications/{$application->id}/approve")->assertForbidden();
    $this->getJson('/api/admin/verification-applications')->assertForbidden();
});
