<?php

use App\Enums\VerificationApplicationStatus;
use App\Models\VerificationApplication;
use App\Services\BusinessDayCalculator;
use App\Services\QueueStalenessCalculator;
use Carbon\Carbon;

beforeEach(function () {
    $this->calculator = new QueueStalenessCalculator(new BusinessDayCalculator);
});

it('given an event item exactly at the 48 hour threshold when staleness is computed then it is not yet stale', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-12 12:00:00'));
    $enteredPendingAt = Carbon::parse('2026-08-10 12:00:00'); // exactly 48h ago

    $staleness = $this->calculator->forEventQueueItem($enteredPendingAt);

    expect($staleness->ageValue)->toBe(48.0)
        ->and($staleness->ageUnit)->toBe('hours')
        ->and($staleness->thresholdValue)->toBe(48.0)
        ->and($staleness->isStale)->toBeFalse();

    Carbon::setTestNow();
});

it('given an event item just past the 48 hour threshold when staleness is computed then it is stale', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-12 12:00:01'));
    $enteredPendingAt = Carbon::parse('2026-08-10 12:00:00'); // 48h and 1 second ago

    $staleness = $this->calculator->forEventQueueItem($enteredPendingAt);

    expect($staleness->isStale)->toBeTrue();

    Carbon::setTestNow();
});

it('given a verification application exactly at the 5 business day threshold when staleness is computed then it is not yet stale', function () {
    // Monday -> next Monday's start-of-week Friday close is exactly 5 business days
    // (Mon, Tue, Wed, Thu, Fri all count, weekend excluded).
    Carbon::setTestNow(Carbon::parse('2026-08-17 00:00:00')); // Monday
    $application = VerificationApplication::factory()->make([
        'created_at' => Carbon::parse('2026-08-10 00:00:00'), // the prior Monday
        'status' => VerificationApplicationStatus::PendingReview,
    ]);

    $staleness = $this->calculator->forVerificationApplication($application);

    expect($staleness->ageValue)->toBe(5.0)
        ->and($staleness->ageUnit)->toBe('business_days')
        ->and($staleness->thresholdValue)->toBe(5.0)
        ->and($staleness->isStale)->toBeFalse();

    Carbon::setTestNow();
});

it('given a verification application just past the 5 business day threshold when staleness is computed then it is stale', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-17 00:00:01')); // one second past 5 business days
    $application = VerificationApplication::factory()->make([
        'created_at' => Carbon::parse('2026-08-10 00:00:00'),
        'status' => VerificationApplicationStatus::PendingReview,
    ]);

    $staleness = $this->calculator->forVerificationApplication($application);

    expect($staleness->isStale)->toBeTrue();

    Carbon::setTestNow();
});
