<?php

namespace Tests\Feature\Infrastructure\Persistence\Eloquent;

use Illuminate\Foundation\Testing\RefreshDatabase;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\ApprovalDecisionModel;
use Tests\TestCase;

class ApprovalDecisionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_a_decision_WHEN_loading_the_decidedByAdmin_relation_THEN_it_resolves_the_deciding_admin(): void
    {
        $admin = AdminUserModel::factory()->create();
        $decision = ApprovalDecisionModel::factory()->create(['decided_by' => $admin->id]);

        $this->assertSame($admin->id, $decision->decidedByAdmin->id);
    }
}
