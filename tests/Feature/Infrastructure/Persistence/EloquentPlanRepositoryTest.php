<?php

namespace Tests\Feature\Infrastructure\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use QOR\App\Infrastructure\Persistence\Eloquent\PlanModel;
use QOR\App\Infrastructure\Persistence\EloquentPlanRepository;
use Tests\TestCase;

class EloquentPlanRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_an_active_and_an_inactive_plan_WHEN_finding_active_plans_THEN_only_the_active_one_is_returned(): void
    {
        $active = PlanModel::factory()->create(['is_active' => true]);
        PlanModel::factory()->create(['is_active' => false]);

        $repository = new EloquentPlanRepository();

        $plans = $repository->findActive();

        $this->assertCount(1, $plans);
        $this->assertSame($active->id, $plans[0]->id);
    }

    public function test_GIVEN_a_plan_flagged_default_free_WHEN_finding_the_default_free_plan_THEN_it_is_returned(): void
    {
        $plan = PlanModel::factory()->create(['is_default_free' => true]);

        $repository = new EloquentPlanRepository();

        $found = $repository->findDefaultFree();

        $this->assertNotNull($found);
        $this->assertSame($plan->id, $found->id);
    }

    public function test_GIVEN_no_plan_flagged_default_free_WHEN_finding_the_default_free_plan_THEN_null_is_returned(): void
    {
        PlanModel::factory()->create(['is_default_free' => false]);

        $repository = new EloquentPlanRepository();

        $this->assertNull($repository->findDefaultFree());
    }
}
