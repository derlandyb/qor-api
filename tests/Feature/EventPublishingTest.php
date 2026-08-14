<?php

use App\Enums\EventStatus;
use App\Enums\VerificationStatus;
use App\Models\Event;
use App\Models\EventRevision;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use Laravel\Sanctum\Sanctum;

function validEventPayload(array $overrides = []): array
{
    return array_merge([
        'action' => 'submit',
        'title' => 'Rock in Rio Warm-up',
        'description' => 'Uma noite de rock pesado.',
        'coverImageUrl' => 'https://example.com/cover.jpg',
        'startDateTime' => now()->addMonth()->toIso8601String(),
        'endDateTime' => null,
        'priceMin' => 40,
        'priceMax' => 80,
        'isFree' => false,
        'currency' => 'BRL',
        'ageRating' => '16',
        'genres' => ['rock'],
        'ticketUrl' => 'https://example.com/tickets',
    ], $overrides);
}

it('given a verified promoter when creating a complete event then a pending approval submission is created', function () {
    $user = User::factory()->create();
    $promoter = Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $venue = Venue::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/admin/publisher/events', validEventPayload(['venueId' => $venue->id]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending_approval');

    $event = Event::query()->findOrFail($response->json('data.id'));
    expect($event->status)->toBe(EventStatus::PendingApproval)
        ->and($event->venue_id)->toBe($venue->id)
        ->and($promoter->events()->pluck('events.id')->all())->toBe([$event->id]);
});

it('given an unverified venue when creating an event then it is forbidden', function () {
    $user = User::factory()->create();
    Venue::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Unverified]);
    Sanctum::actingAs($user);

    $this->postJson('/api/admin/publisher/events', validEventPayload())
        ->assertForbidden();

    expect(Event::query()->count())->toBe(0);
});

it('given a verified promoter when saving a draft with only a title then it succeeds without the other required fields', function () {
    $user = User::factory()->create();
    Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    Sanctum::actingAs($user);

    $this->postJson('/api/admin/publisher/events', ['action' => 'draft', 'title' => 'Rascunho'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.title', 'Rascunho')
        // `price` must always be present (not omitted) even with no price state yet — an
        // admin edit-form regression (Bug 7A) happened precisely because this key went missing.
        ->assertJsonPath('data.price.isFree', false)
        ->assertJsonPath('data.price.min', null);
});

it('given a submit action missing required fields when creating an event then it returns field level errors', function () {
    $user = User::factory()->create();
    Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    Sanctum::actingAs($user);

    $this->postJson('/api/admin/publisher/events', ['action' => 'submit', 'title' => 'Incompleto'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['description', 'coverImageUrl', 'startDateTime', 'venueId']);
});

it('given a venue account when creating an event with a different venueId then the server overrides it to the venue\'s own row', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $otherVenue = Venue::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/admin/publisher/events', validEventPayload(['venueId' => $otherVenue->id]))
        ->assertCreated();

    $event = Event::query()->findOrFail($response->json('data.id'));
    expect($event->venue_id)->toBe($venue->id);
});

it('given a published event with a pending edit when the edit form data is fetched then the revision fields are returned', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create(['status' => EventStatus::Published]);
    $revision = EventRevision::factory()->for($event)->create(['title' => 'Novo Título']);
    Sanctum::actingAs($user);

    $this->getJson("/api/admin/publisher/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('event.title', $event->title)
        ->assertJsonPath('pendingRevision.title', 'Novo Título')
        ->assertJsonPath('pendingRevision.id', (string) $revision->id);
});

it('given a published event when an edit is submitted then a revision is created and the live event is untouched', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create(['status' => EventStatus::Published, 'title' => 'Título Original']);
    Sanctum::actingAs($user);

    $this->patchJson("/api/admin/publisher/events/{$event->id}", validEventPayload(['title' => 'Título Editado', 'venueId' => $venue->id]))
        ->assertOk()
        // The live Event row's own fields are byte-for-byte unchanged (PUBLISH-002/003's core guarantee).
        ->assertJsonPath('data.title', 'Título Original')
        ->assertJsonPath('data.status', 'published');

    $event->refresh();
    expect($event->title)->toBe('Título Original')
        ->and($event->pendingRevision)->not->toBeNull()
        ->and($event->pendingRevision->title)->toBe('Título Editado');
});

it('given a draft event when it is edited directly then its own fields are updated with no revision created', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create(['status' => EventStatus::Draft, 'title' => 'Rascunho Original']);
    Sanctum::actingAs($user);

    $this->patchJson("/api/admin/publisher/events/{$event->id}", validEventPayload(['action' => 'draft', 'title' => 'Rascunho Editado', 'venueId' => $venue->id]))
        ->assertOk()
        ->assertJsonPath('data.title', 'Rascunho Editado')
        ->assertJsonPath('data.status', 'draft');

    $event->refresh();
    expect($event->title)->toBe('Rascunho Editado')
        ->and($event->pendingRevision)->toBeNull();
});

it('given a cancelled event when an edit is submitted then it conflicts', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create(['status' => EventStatus::Cancelled]);
    Sanctum::actingAs($user);

    $this->patchJson("/api/admin/publisher/events/{$event->id}", validEventPayload(['venueId' => $venue->id]))
        ->assertStatus(409);
});

it('given an event owned by another publisher when it is accessed then it is forbidden', function () {
    $owner = Venue::factory()->create(['verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($owner)->create();

    $intruder = User::factory()->create();
    Venue::factory()->create(['user_id' => $intruder->id, 'verification_status' => VerificationStatus::Verified]);
    Sanctum::actingAs($intruder);

    $this->getJson("/api/admin/publisher/events/{$event->id}")
        ->assertForbidden()
        ->assertJsonPath('message', 'Você não tem permissão para executar esta ação.');
});

test('given no Sanctum token when the publisher create endpoint is called then it returns unauthorized', function () {
    $this->postJson('/api/admin/publisher/events', [])->assertUnauthorized();
});

it('given a description over 5000 characters when creating an event then it returns a field level error', function () {
    $user = User::factory()->create();
    Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    Sanctum::actingAs($user);

    $this->postJson('/api/admin/publisher/events', validEventPayload(['description' => str_repeat('a', 5001)]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['description']);
});

it('given a description containing HTML tags when creating an event then the tags are stripped before saving', function () {
    $user = User::factory()->create();
    Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $venue = Venue::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/admin/publisher/events', validEventPayload([
        'venueId' => $venue->id,
        'description' => '<script>alert(1)</script>Uma noite de rock pesado.',
    ]))->assertCreated();

    $event = Event::query()->findOrFail($response->json('data.id'));
    expect($event->description)->toBe('alert(1)Uma noite de rock pesado.');
});
