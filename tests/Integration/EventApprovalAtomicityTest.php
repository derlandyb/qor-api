<?php

use App\Enums\EventRevisionStatus;
use App\Enums\EventStatus;
use App\Enums\VerificationStatus;
use App\Models\Event;
use App\Models\EventRevision;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

it('given a published event with a real pending revision when it is approved then the field copy and revision delete land atomically', function () {
    $venue = Venue::factory()->create(['verification_status' => VerificationStatus::Verified]);
    $event = Event::factory()->for($venue)->create([
        'status' => EventStatus::Published,
        'title' => 'Título Original',
        'price_min' => 10,
        'price_max' => 20,
    ]);
    $revision = EventRevision::factory()->for($event)->create([
        'title' => 'Título Aprovado',
        'price_min' => 50,
        'price_max' => 90,
        'status' => EventRevisionStatus::PendingApproval,
    ]);
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->postJson("/api/admin/events/{$event->id}/approve")->assertOk();

    $eventRow = DB::table('events')->where('id', $event->id)->first();
    $revisionExists = DB::table('event_revisions')->where('event_id', $event->id)->exists();

    expect($eventRow->title)->toBe('Título Aprovado')
        ->and((float) $eventRow->price_min)->toBe(50.0)
        ->and((float) $eventRow->price_max)->toBe(90.0)
        ->and($eventRow->status)->toBe('published')
        ->and($revisionExists)->toBeFalse();
});
