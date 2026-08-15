<?php

use App\Enums\EventStatus;
use App\Enums\VerificationStatus;
use App\Models\Artist;
use App\Models\Event;
use App\Models\EventRevision;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function validEventPayload(array $overrides = []): array
{
    return array_merge([
        'action' => 'submit',
        'title' => 'Rock in Rio Warm-up',
        'description' => 'Uma noite de rock pesado.',
        'coverImage' => UploadedFile::fake()->create('cover.jpg', 100, 'image/jpeg'),
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

beforeEach(function () {
    Storage::fake('s3');
});

it('given a verified promoter when creating a complete event then a pending approval submission is created', function () {
    $user = User::factory()->create();
    $promoter = Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $venue = Venue::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/publisher/events', validEventPayload(['venueId' => $venue->id]))
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

    $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/publisher/events', validEventPayload())
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
        // `price` must always be present (not omitted) even with no price state yet — an admin
        // edit-form crash happened precisely because this key went missing.
        ->assertJsonPath('data.price.isFree', false)
        ->assertJsonPath('data.price.min', null);
});

it('given a submit action missing required fields when creating an event then it returns field level errors', function () {
    $user = User::factory()->create();
    Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    Sanctum::actingAs($user);

    $this->postJson('/api/admin/publisher/events', ['action' => 'submit', 'title' => 'Incompleto'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['description', 'coverImage', 'startDateTime', 'venueId']);
});

it('given a venue account when creating an event with a different venueId then the server overrides it to the venue\'s own row', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $otherVenue = Venue::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/publisher/events', validEventPayload(['venueId' => $otherVenue->id]))
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

    $this->withHeaders(['Accept' => 'application/json'])->patch("/api/admin/publisher/events/{$event->id}", validEventPayload(['title' => 'Título Editado', 'venueId' => $venue->id]))
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

    $this->withHeaders(['Accept' => 'application/json'])->patch("/api/admin/publisher/events/{$event->id}", validEventPayload(['action' => 'draft', 'title' => 'Rascunho Editado', 'venueId' => $venue->id]))
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

    $this->withHeaders(['Accept' => 'application/json'])->patch("/api/admin/publisher/events/{$event->id}", validEventPayload(['venueId' => $venue->id]))
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

    $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/publisher/events', validEventPayload(['description' => str_repeat('a', 5001)]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['description']);
});

it('given a description containing HTML tags when creating an event then the tags are stripped before saving', function () {
    $user = User::factory()->create();
    Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $venue = Venue::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/publisher/events', validEventPayload([
        'venueId' => $venue->id,
        'description' => '<script>alert(1)</script>Uma noite de rock pesado.',
    ]))->assertCreated();

    $event = Event::query()->findOrFail($response->json('data.id'));
    expect($event->description)->toBe('alert(1)Uma noite de rock pesado.');
});

it('given a valid cover image file when creating an event then it is stored on s3 and the response resolves a public url', function () {
    $user = User::factory()->create();
    Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $venue = Venue::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/publisher/events', validEventPayload(['venueId' => $venue->id]))
        ->assertCreated();

    $event = Event::query()->findOrFail($response->json('data.id'));
    expect($event->cover_image_path)->not->toBeNull();
    Storage::disk('s3')->assertExists($event->cover_image_path);
    expect($response->json('data.coverImageUrl'))->toBe(Storage::disk('s3')->url($event->cover_image_path));
});

it('given a draft event with a stored cover image when it is resaved without a new file then the existing cover image path is preserved', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create([
        'status' => EventStatus::Draft,
        'cover_image_path' => 'events/covers/existing.jpg',
    ]);
    Sanctum::actingAs($user);

    $payload = validEventPayload(['action' => 'draft', 'venueId' => $venue->id]);
    unset($payload['coverImage']);

    $this->withHeaders(['Accept' => 'application/json'])->patch("/api/admin/publisher/events/{$event->id}", $payload)->assertOk();

    expect($event->fresh()->cover_image_path)->toBe('events/covers/existing.jpg');
});

it('given a published event with a stored cover image when an edit is submitted without a new file then it is accepted and the path is forwarded onto the revision', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create([
        'status' => EventStatus::Published,
        'cover_image_path' => 'events/covers/live.jpg',
    ]);
    Sanctum::actingAs($user);

    $payload = validEventPayload(['title' => 'Novo Título', 'venueId' => $venue->id]);
    unset($payload['coverImage']);

    $this->withHeaders(['Accept' => 'application/json'])->patch("/api/admin/publisher/events/{$event->id}", $payload)->assertOk();

    expect($event->fresh()->cover_image_path)->toBe('events/covers/live.jpg')
        ->and($event->fresh()->pendingRevision->cover_image_path)->toBe('events/covers/live.jpg');
});

it('given a submit with neither a new file nor an existing stored cover image when creating an event then it returns a field level error for coverImage', function () {
    $user = User::factory()->create();
    Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    Sanctum::actingAs($user);

    $payload = validEventPayload();
    unset($payload['coverImage']);

    $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/publisher/events', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['coverImage']);
});

it('given a cover image with a disallowed mime type when creating an event then it returns a field level error', function () {
    $user = User::factory()->create();
    Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $venue = Venue::factory()->create();
    Sanctum::actingAs($user);

    $payload = validEventPayload([
        'venueId' => $venue->id,
        'coverImage' => UploadedFile::fake()->create('cover.pdf', 100, 'application/pdf'),
    ]);

    $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/publisher/events', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['coverImage']);

    expect(Event::query()->count())->toBe(0);
});

it('given a cover image over the size limit when creating an event then it returns a field level error', function () {
    $user = User::factory()->create();
    Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $venue = Venue::factory()->create();
    Sanctum::actingAs($user);

    $payload = validEventPayload([
        'venueId' => $venue->id,
        'coverImage' => UploadedFile::fake()->create('cover.jpg', 6000, 'image/jpeg'),
    ]);

    $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/publisher/events', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['coverImage']);
});

it('given an s3 upload failure when creating an event then it returns a structured error and no event is created', function () {
    $user = User::factory()->create();
    Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $venue = Venue::factory()->create();
    Sanctum::actingAs($user);

    Storage::shouldReceive('disk')->with('s3')->andReturn(
        tap(Mockery::mock(Filesystem::class), fn ($mock) => $mock->shouldReceive('putFile')->andThrow(new RuntimeException('s3 down')))
    );

    $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/publisher/events', validEventPayload(['venueId' => $venue->id]))
        ->assertStatus(502)
        ->assertJsonStructure(['message']);

    expect(Event::query()->count())->toBe(0);
});

it('given an s3 upload failure when editing a published event then it returns a structured error and the live event/revision are unaffected', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create(['status' => EventStatus::Published, 'title' => 'Original']);
    Sanctum::actingAs($user);

    Storage::shouldReceive('disk')->with('s3')->andReturn(
        tap(Mockery::mock(Filesystem::class), fn ($mock) => $mock->shouldReceive('putFile')->andThrow(new RuntimeException('s3 down')))
    );

    $this->withHeaders(['Accept' => 'application/json'])->patch("/api/admin/publisher/events/{$event->id}", validEventPayload(['venueId' => $venue->id]))
        ->assertStatus(502);

    expect($event->fresh()->title)->toBe('Original')
        ->and($event->fresh()->pendingRevision)->toBeNull();
});

it('given genres and artistIds sent as JSON-encoded strings (the Web client\'s multipart representation) when creating an event then both decode into arrays', function () {
    $user = User::factory()->create();
    Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $venue = Venue::factory()->create();
    $artist = Artist::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/publisher/events', validEventPayload([
        'venueId' => $venue->id,
        'genres' => json_encode(['rock', 'punk']),
        'artistIds' => json_encode([$artist->id]),
    ]))->assertCreated();

    $event = Event::query()->findOrFail($response->json('data.id'));
    expect($event->genres)->toBe(['rock', 'punk'])
        ->and($event->artists->pluck('id')->all())->toBe([$artist->id]);
});

it('given a published event with attached artists when its artistIds selection is explicitly cleared to an empty JSON array then every artist association is removed', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create(['user_id' => $user->id, 'verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create(['status' => EventStatus::Draft]);
    $artist = Artist::factory()->create();
    $event->artists()->attach($artist->id);
    Sanctum::actingAs($user);

    $this->withHeaders(['Accept' => 'application/json'])->patch("/api/admin/publisher/events/{$event->id}", validEventPayload([
        'action' => 'draft',
        'venueId' => $venue->id,
        'artistIds' => json_encode([]),
    ]))->assertOk();

    expect($event->artists()->count())->toBe(0);
});
