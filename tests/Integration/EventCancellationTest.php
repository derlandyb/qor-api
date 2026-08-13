<?php

use App\Enums\EventStatus;
use App\Enums\VerificationStatus;
use App\Models\Event;
use App\Models\EventRevision;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

it('given an unverified or pending review promoter or venue when every publisher route is hit then all return 403 and write no rows', function (?string $profileType, ?VerificationStatus $status) {
    $user = User::factory()->create();
    $venue = Venue::factory()->create();
    $event = Event::factory()->for($venue)->create();

    if ($profileType === 'promoter') {
        Promoter::factory()->create(['user_id' => $user->id, 'verification_status' => $status]);
    } elseif ($profileType === 'venue') {
        Venue::factory()->create(['user_id' => $user->id, 'verification_status' => $status]);
    }
    Sanctum::actingAs($user);

    $this->postJson('/api/admin/publisher/events', ['action' => 'draft', 'title' => 'x'])->assertForbidden();
    $this->getJson('/api/admin/publisher/events')->assertForbidden();
    $this->getJson("/api/admin/publisher/events/{$event->id}")->assertForbidden();
    $this->patchJson("/api/admin/publisher/events/{$event->id}", ['action' => 'draft', 'title' => 'x'])->assertForbidden();
    $this->postJson("/api/admin/publisher/events/{$event->id}/cancel")->assertForbidden();

    // No row written by any of the above — the gate denies before any controller code runs.
    expect(Event::query()->count())->toBe(1) // only the pre-seeded one
        ->and($event->fresh()->status)->toBe($event->status);
})->with([
    'no profile at all' => [null, null],
    'unverified promoter' => ['promoter', VerificationStatus::Unverified],
    'pending review promoter' => ['promoter', VerificationStatus::PendingReview],
    'unverified venue' => ['venue', VerificationStatus::Unverified],
    'pending review venue' => ['venue', VerificationStatus::PendingReview],
]);

it('given a published event with a real pending revision when cancel runs then both writes commit atomically in the same transaction', function () {
    $venue = Venue::factory()->create();
    $event = Event::factory()->for($venue)->create(['status' => EventStatus::Published]);
    EventRevision::factory()->for($event)->create();

    $event->cancel();

    expect(DB::table('events')->where('id', $event->id)->value('status'))->toBe('cancelled')
        ->and(DB::table('event_revisions')->where('event_id', $event->id)->exists())->toBeFalse();
});

it('given a forced failure deleting the pending revision when cancel runs then the whole transaction rolls back including the status write', function () {
    $venue = Venue::factory()->create();
    $event = Event::factory()->for($venue)->create(['status' => EventStatus::Published]);
    $revision = EventRevision::factory()->for($event)->create();

    // Forces the DELETE inside Event::cancel()'s transaction to fail at the DB level, as a
    // negative control proving the `status = cancelled` write rolls back too — the core "one
    // operation" guarantee PUBLISH-007 depends on.
    DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION prevent_event_revision_delete_for_test() RETURNS trigger AS $$
        BEGIN
            RAISE EXCEPTION 'forced failure for negative-control test';
        END;
        $$ LANGUAGE plpgsql;

        CREATE TRIGGER prevent_delete_for_test
        BEFORE DELETE ON event_revisions
        FOR EACH ROW EXECUTE FUNCTION prevent_event_revision_delete_for_test();
    SQL);

    try {
        expect(fn () => $event->cancel())->toThrow(QueryException::class);
    } finally {
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_delete_for_test ON event_revisions');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_event_revision_delete_for_test()');
    }

    expect(DB::table('events')->where('id', $event->id)->value('status'))->toBe('published')
        ->and(DB::table('event_revisions')->where('id', $revision->id)->exists())->toBeTrue();
});
