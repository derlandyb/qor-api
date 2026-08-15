<?php

use App\Enums\EventRevisionStatus;
use App\Enums\EventStatus;
use App\Enums\VerificationStatus;
use App\Models\Event;
use App\Models\EventRevision;
use App\Models\User;
use App\Models\Venue;
use Laravel\Sanctum\Sanctum;

it('given a moderator when approving a pending event edit then the public event updates and the decision is audited', function () {
    $venue = Venue::factory()->create(['verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create([
        'status' => EventStatus::Published,
        'title' => 'Título Original',
        'reviewer_feedback' => null,
    ]);
    $revision = EventRevision::factory()->for($event)->create([
        'title' => 'Título Editado',
        'status' => EventRevisionStatus::PendingApproval,
    ]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson("/api/admin/events/{$event->id}/approve")
        ->assertOk()
        ->assertJsonPath('event.title', 'Título Editado')
        ->assertJsonPath('event.status', 'published');

    // The public event updates — the live row now carries the previously-proposed values.
    $this->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Título Editado');

    $event->refresh();
    expect($event->title)->toBe('Título Editado')
        ->and($event->status)->toBe(EventStatus::Published)
        // The decision is audited: the resolved revision no longer exists, reviewer_feedback is
        // cleared (a clean decision record, not stale leftover feedback), and the item is gone
        // from the moderation queue on next fetch.
        ->and($event->reviewer_feedback)->toBeNull()
        ->and(EventRevision::query()->where('event_id', $event->id)->exists())->toBeFalse();

    $queue = $this->getJson('/api/admin/moderation/event-queue')->assertOk();
    expect(collect($queue->json('data'))->pluck('event.id'))->not->toContain((string) $event->id);
});

it('given a moderator when approving a pending event edit then the revisions cover image path copies verbatim onto the live event', function () {
    $venue = Venue::factory()->create(['verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create([
        'status' => EventStatus::Published,
        'cover_image_path' => 'events/covers/old.jpg',
    ]);
    EventRevision::factory()->for($event)->create([
        'status' => EventRevisionStatus::PendingApproval,
        'cover_image_path' => 'events/covers/new.jpg',
    ]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson("/api/admin/events/{$event->id}/approve")->assertOk();

    expect($event->fresh()->cover_image_path)->toBe('events/covers/new.jpg');
});

it('given a publisher when reviewing another publishers submission then it is forbidden', function () {
    $owner = Venue::factory()->create(['verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($owner)->create(['status' => EventStatus::PendingApproval]);

    $otherPublisherUser = User::factory()->create();
    Venue::factory()->create(['user_id' => $otherPublisherUser->id, 'verification_status' => VerificationStatus::Verified]);
    Sanctum::actingAs($otherPublisherUser);

    $this->postJson("/api/admin/events/{$event->id}/approve")->assertForbidden();
    $this->postJson("/api/admin/events/{$event->id}/request-changes", ['feedback' => 'Precisa de correções.'])->assertForbidden();

    expect($event->fresh()->status)->toBe(EventStatus::PendingApproval);
});

it('given a moderator when approving a new event submission then it is published with no revision involved', function () {
    $venue = Venue::factory()->create(['verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create(['status' => EventStatus::PendingApproval]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson("/api/admin/events/{$event->id}/approve")
        ->assertOk()
        ->assertJsonPath('event.status', 'published');

    expect($event->fresh()->status)->toBe(EventStatus::Published);
});

it('given a moderator when requesting changes on a pending event edit then only the revision is affected', function () {
    $venue = Venue::factory()->create(['verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create(['status' => EventStatus::Published, 'title' => 'Título Publicado']);
    $revision = EventRevision::factory()->for($event)->create(['status' => EventRevisionStatus::PendingApproval]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson("/api/admin/events/{$event->id}/request-changes", ['feedback' => 'Falta imagem de capa.'])
        ->assertOk()
        // The live Event row is not touched at all when a revision exists.
        ->assertJsonPath('event.title', 'Título Publicado')
        ->assertJsonPath('event.status', 'published');

    expect($event->fresh()->title)->toBe('Título Publicado')
        ->and($event->fresh()->status)->toBe(EventStatus::Published)
        ->and($revision->fresh()->status)->toBe(EventRevisionStatus::ChangesRequested)
        ->and($revision->fresh()->reviewer_feedback)->toBe('Falta imagem de capa.');
});

it('given a moderator when requesting changes on a new submission then the event itself moves to changes requested', function () {
    $venue = Venue::factory()->create(['verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create(['status' => EventStatus::PendingApproval]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson("/api/admin/events/{$event->id}/request-changes", ['feedback' => 'Data incorreta.'])
        ->assertOk()
        ->assertJsonPath('event.status', 'changes_requested');

    expect($event->fresh()->status)->toBe(EventStatus::ChangesRequested)
        ->and($event->fresh()->reviewer_feedback)->toBe('Data incorreta.');
});

it('given blank feedback when requesting changes then it returns a field level error', function () {
    $venue = Venue::factory()->create(['verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create(['status' => EventStatus::PendingApproval]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson("/api/admin/events/{$event->id}/request-changes", ['feedback' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('feedback');
});

it('given an already published event with no pending item when it is approved again then it conflicts', function () {
    $venue = Venue::factory()->create(['verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create(['status' => EventStatus::Published]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson("/api/admin/events/{$event->id}/approve")->assertStatus(409);
    $this->postJson("/api/admin/events/{$event->id}/request-changes", ['feedback' => 'tarde demais'])->assertStatus(409);
});

it('given feedback over 2000 characters when requesting changes then it returns a field level error', function () {
    $venue = Venue::factory()->create(['verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create(['status' => EventStatus::PendingApproval]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson("/api/admin/events/{$event->id}/request-changes", ['feedback' => str_repeat('a', 2001)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('feedback');
});

it('given feedback containing HTML tags when requesting changes then the tags are stripped before saving', function () {
    $venue = Venue::factory()->create(['verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create(['status' => EventStatus::PendingApproval]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson("/api/admin/events/{$event->id}/request-changes", [
        'feedback' => '<script>alert(1)</script>Falta imagem de capa.',
    ])->assertOk();

    expect($event->fresh()->reviewer_feedback)->toBe('alert(1)Falta imagem de capa.');
});

it('given a non-admin account when the event queue or review actions are called then it is forbidden', function () {
    $venue = Venue::factory()->create(['verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create(['status' => EventStatus::PendingApproval]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/admin/moderation/event-queue')->assertForbidden();
    $this->getJson('/api/admin/moderation/verification-queue')->assertForbidden();
    $this->getJson('/api/admin/moderation/verified-accounts')->assertForbidden();
    $this->postJson("/api/admin/events/{$event->id}/approve")->assertForbidden();
});
