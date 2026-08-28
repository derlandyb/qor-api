<?php

namespace Tests\Feature\Infrastructure\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use QOR\App\Domain\Approval\ApprovalDecision;
use QOR\App\Domain\Approval\Enum\ApprovalDecidableType;
use QOR\App\Domain\Approval\Enum\ApprovalOutcome;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\EventModel;
use QOR\App\Infrastructure\Persistence\Eloquent\PromoterModel;
use QOR\App\Infrastructure\Persistence\Eloquent\VenueModel;
use QOR\App\Infrastructure\Persistence\EloquentApprovalDecisionRepository;
use Tests\TestCase;

class EloquentApprovalDecisionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_pending_venues_and_promoters_WHEN_finding_pending_accounts_THEN_both_are_returned(): void
    {
        $venue = VenueModel::factory()->create();
        $promoter = PromoterModel::factory()->create();
        VenueModel::factory()->approved()->create();

        $repository = new EloquentApprovalDecisionRepository();

        $pending = $repository->findPendingAccounts();

        $this->assertCount(2, $pending);
        $types = array_map(fn ($p) => $p->type, $pending);
        $this->assertContains(ApprovalDecidableType::Venue, $types);
        $this->assertContains(ApprovalDecidableType::Promoter, $types);
    }

    public function test_GIVEN_pending_review_events_WHEN_finding_pending_events_THEN_only_those_ids_are_returned(): void
    {
        $genreId = DB::table('genres')->insertGetId(['name' => 'Rock', 'slug' => 'rock', 'created_at' => now(), 'updated_at' => now()]);

        $pendingEvent = EventModel::factory()->pendingReview()->create(['genre_id' => $genreId]);
        EventModel::factory()->published()->create(['genre_id' => $genreId]);

        $repository = new EloquentApprovalDecisionRepository();

        $pendingIds = $repository->findPendingEvents();

        $this->assertSame([$pendingEvent->id], $pendingIds);
    }

    public function test_GIVEN_a_decision_WHEN_saving_THEN_it_is_persisted_and_assigned_an_id(): void
    {
        $admin = AdminUserModel::factory()->create();
        $decidedAt = now()->subMinute()->toDateTimeImmutable();

        $decision = new ApprovalDecision(
            id: null,
            decidableType: ApprovalDecidableType::Venue,
            decidableId: 1,
            outcome: ApprovalOutcome::Rejected,
            decidedBy: $admin->id,
            decidedAt: $decidedAt,
            reason: 'Documentação incompleta.',
        );

        $repository = new EloquentApprovalDecisionRepository();

        $saved = $repository->save($decision);

        $this->assertNotNull($saved->id);
        $this->assertSame(ApprovalOutcome::Rejected, $saved->outcome);
        $this->assertSame('Documentação incompleta.', $saved->reason);
        $this->assertDatabaseHas('approval_decisions', [
            'id' => $saved->id,
            'decidable_type' => ApprovalDecidableType::Venue->value,
            'outcome' => ApprovalOutcome::Rejected->value,
        ]);
    }
}
