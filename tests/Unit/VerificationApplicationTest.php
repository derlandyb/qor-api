<?php

use App\Enums\AccountType;
use App\Enums\VerificationApplicationStatus;
use App\Enums\VerificationStatus;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use App\Models\VerificationApplication;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('given a pending application when it is approved then it becomes verified and the promoter is verified', function () {
    $promoter = Promoter::factory()->create(['verification_status' => VerificationStatus::PendingReview]);
    $application = VerificationApplication::factory()->create([
        'promoter_id' => $promoter->id,
        'venue_id' => null,
        'status' => VerificationApplicationStatus::PendingReview,
    ]);
    $reviewer = User::factory()->create();

    $application->approve($reviewer);

    expect($application->status)->toBe(VerificationApplicationStatus::Verified)
        ->and($application->reviewed_by)->toBe($reviewer->id)
        ->and($application->reviewed_at)->not->toBeNull()
        ->and($promoter->fresh()->verification_status)->toBe(VerificationStatus::Verified);
});

it('given a pending application when it is rejected then it becomes rejected and the promoter folds back to unverified', function () {
    $promoter = Promoter::factory()->create(['verification_status' => VerificationStatus::PendingReview]);
    $application = VerificationApplication::factory()->create([
        'promoter_id' => $promoter->id,
        'venue_id' => null,
        'status' => VerificationApplicationStatus::PendingReview,
    ]);
    $reviewer = User::factory()->create();

    $application->reject($reviewer, 'Documento ilegível.');

    expect($application->status)->toBe(VerificationApplicationStatus::Rejected)
        ->and($application->rejection_feedback)->toBe('Documento ilegível.')
        ->and($promoter->fresh()->verification_status)->toBe(VerificationStatus::Unverified);
});

it('given a non pending application when approve or reject is called then it aborts with a conflict', function (VerificationApplicationStatus $startingStatus, string $method) {
    $promoter = Promoter::factory()->create();
    $application = VerificationApplication::factory()->create([
        'promoter_id' => $promoter->id,
        'venue_id' => null,
        'status' => $startingStatus,
    ]);
    $reviewer = User::factory()->create();

    $call = $method === 'approve'
        ? fn () => $application->approve($reviewer)
        : fn () => $application->reject($reviewer, 'feedback');

    expect($call)->toThrow(HttpException::class);

    try {
        $call();
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(409);
    }
})->with([
    'verified -> approve' => [VerificationApplicationStatus::Verified, 'approve'],
    'verified -> reject' => [VerificationApplicationStatus::Verified, 'reject'],
    'rejected -> approve' => [VerificationApplicationStatus::Rejected, 'approve'],
    'rejected -> reject' => [VerificationApplicationStatus::Rejected, 'reject'],
]);

it('given an account type and verification status when the manage-events gate is checked then only a verified promoter or venue passes', function (AccountType $accountType, ?string $profileType, ?VerificationStatus $status, bool $expected) {
    $user = User::factory()->create(['account_type' => $accountType]);

    if ($profileType === 'promoter') {
        Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => $status]);
    } elseif ($profileType === 'venue') {
        Venue::factory()->create(['user_id' => $user->id, 'verification_status' => $status]);
    }

    expect(Gate::forUser($user->fresh())->allows('manage-events'))->toBe($expected);
})->with([
    'standard, no profile' => [AccountType::Standard, null, null, false],
    'promoter, unverified' => [AccountType::Promoter, 'promoter', VerificationStatus::Unverified, false],
    'promoter, pending review' => [AccountType::Promoter, 'promoter', VerificationStatus::PendingReview, false],
    'promoter, verified' => [AccountType::Promoter, 'promoter', VerificationStatus::Verified, true],
    'venue, unverified' => [AccountType::Venue, 'venue', VerificationStatus::Unverified, false],
    'venue, pending review' => [AccountType::Venue, 'venue', VerificationStatus::PendingReview, false],
    'venue, verified' => [AccountType::Venue, 'venue', VerificationStatus::Verified, true],
]);
