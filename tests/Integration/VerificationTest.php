<?php

use App\Enums\EventStatus;
use App\Enums\VerificationStatus;
use App\Models\Event;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;

it('given revoked verification when published events exist then they remain published and new event management is forbidden', function () {
    $venueUser = User::factory()->create();
    // Venue over Promoter here — events carry a direct venue_id FK, letting this test assert
    // the events row's own columns untouched via a raw DB query rather than through the
    // event_promoter pivot, which is what "completely untouched" is meant to prove.
    $venue = Venue::factory()->create(['user_id' => $venueUser->id, 'verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create(['status' => EventStatus::Published]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson("/api/admin/venues/{$venue->id}/revoke", ['reason' => 'Documentação vencida.'])->assertOk();

    $venueRow = DB::table('venues')->where('id', $venue->id)->first();
    expect($venueRow->verification_status)->toBe('unverified')
        ->and($venueRow->revocation_reason)->toBe('Documentação vencida.');

    $eventRow = DB::table('events')->where('id', $event->id)->first();
    expect($eventRow->venue_id)->toBe($venue->id)
        ->and($eventRow->status)->toBe('published');

    expect(Gate::forUser($venueUser->fresh())->denies('manage-events'))->toBeTrue();
});

it('given a blank revocation reason when a venue is revoked then it returns a field level error and nothing changes', function () {
    $venueUser = User::factory()->create();
    $venue = Venue::factory()->create(['user_id' => $venueUser->id, 'verification_status' => VerificationStatus::Verified]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson("/api/admin/venues/{$venue->id}/revoke")
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');

    $venueRow = DB::table('venues')->where('id', $venue->id)->first();
    expect($venueRow->verification_status)->toBe('verified')
        ->and($venueRow->revocation_reason)->toBeNull();
});

it('given a user with an existing promoter profile when a second promoter claims the same user_id then the database rejects it', function () {
    $user = User::factory()->create();
    Promoter::factory()->create(['user_id' => $user->id]);

    expect(fn () => Promoter::factory()->create(['user_id' => $user->id]))
        ->toThrow(QueryException::class);
});

it('given a user with an existing venue profile when a second venue claims the same user_id then the database rejects it', function () {
    $user = User::factory()->create();
    Venue::factory()->create(['user_id' => $user->id]);

    expect(fn () => Venue::factory()->create(['user_id' => $user->id]))
        ->toThrow(QueryException::class);
});
