<?php

use App\Enums\EventRevisionStatus;
use App\Enums\EventStatus;
use App\Enums\VerificationApplicationStatus;
use App\Enums\VerificationStatus;
use App\Models\Event;
use App\Models\EventRevision;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use App\Models\VerificationApplication;
use Laravel\Sanctum\Sanctum;

it('given no pending verification applications when the queue is fetched then queueStaleness is null', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->getJson('/api/admin/moderation/verification-queue')
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('queueStaleness', null);
});

it('given pending verification applications when the queue is fetched then each row carries staleness oldest first', function () {
    $older = VerificationApplication::factory()->create([
        'status' => VerificationApplicationStatus::PendingReview,
        'created_at' => now()->subDays(10),
    ]);
    $newer = VerificationApplication::factory()->create([
        'status' => VerificationApplicationStatus::PendingReview,
        'created_at' => now()->subHour(),
    ]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->getJson('/api/admin/moderation/verification-queue')->assertOk();

    $ids = collect($response->json('data'))->pluck('application.id')->all();
    expect($ids)->toBe([(string) $older->id, (string) $newer->id])
        ->and($response->json('queueStaleness.ageUnit'))->toBe('business_days')
        ->and($response->json('queueStaleness.isStale'))->toBeTrue();
});

it('given no pending events when the event queue is fetched then queueStaleness is null', function () {
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->getJson('/api/admin/moderation/event-queue')
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('queueStaleness', null);
});

it('given a new submission and a published edit when the event queue is fetched then each is typed with a diff for the edit only', function () {
    $venue = Venue::factory()->create(['verification_status' => VerificationStatus::Verified]);
    $newEvent = Event::factory()->for($venue)->create(['status' => EventStatus::PendingApproval]);
    $publishedEvent = Event::factory()->for($venue)->create(['status' => EventStatus::Published, 'title' => 'Original']);
    EventRevision::factory()->for($publishedEvent)->create([
        'title' => 'Editado',
        'status' => EventRevisionStatus::PendingApproval,
    ]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->getJson('/api/admin/moderation/event-queue')->assertOk();
    $items = collect($response->json('data'))->keyBy('event.id');

    $newItem = $items[(string) $newEvent->id];
    expect($newItem['type'])->toBe('new')
        ->and($newItem['diff'])->toBeNull()
        ->and($newItem['revision'])->toBeNull();

    $editItem = $items[(string) $publishedEvent->id];
    expect($editItem['type'])->toBe('edit')
        ->and($editItem['revision'])->not->toBeNull();

    $titleDiff = collect($editItem['diff'])->firstWhere('field', 'title');
    expect($titleDiff)->not->toBeNull()
        ->and($titleDiff['before'])->toBe('Original')
        ->and($titleDiff['after'])->toBe('Editado');
});

it('given verified promoters and venues when the verified accounts list is fetched then both entity types appear', function () {
    $promoterUser = User::factory()->create();
    $promoter = Promoter::factory()->create(['user_id' => $promoterUser->id, 'verification_status' => VerificationStatus::Verified]);
    $venueUser = User::factory()->create();
    $venue = Venue::factory()->create(['user_id' => $venueUser->id, 'verification_status' => VerificationStatus::Verified]);
    Venue::factory()->create(['verification_status' => VerificationStatus::Unverified]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $response = $this->getJson('/api/admin/moderation/verified-accounts')->assertOk();
    $entityTypes = collect($response->json('data'))->pluck('entityType')->all();

    expect($entityTypes)->toContain('promoter')
        ->and($entityTypes)->toContain('venue')
        ->and(count($response->json('data')))->toBe(2);
});
