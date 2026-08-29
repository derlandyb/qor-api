<?php

namespace Tests\Unit\Domain\Billing\UseCase;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Billing\Plan;
use QOR\App\Domain\Billing\PlanRepository;
use QOR\App\Domain\Billing\UseCase\CreatePlan;

class CreatePlanTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_GIVEN_valid_plan_data_WHEN_creating_THEN_it_is_persisted_as_active(): void
    {
        $repository = Mockery::mock(PlanRepository::class);
        $repository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Plan $plan) => $plan->id === null
                && $plan->name === 'Pro'
                && $plan->monthlyPrice === 99.9
                && $plan->annualPrice === 999.0
                && $plan->publishQuota === 20
                && $plan->isActive === true))
            ->andReturnUsing(fn (Plan $plan) => new Plan(
                id: 1,
                name: $plan->name,
                monthlyPrice: $plan->monthlyPrice,
                annualPrice: $plan->annualPrice,
                publishQuota: $plan->publishQuota,
                isActive: $plan->isActive,
            ));

        $useCase = new CreatePlan($repository);

        $result = $useCase->execute('Pro', 99.9, 20, 999.0);

        $this->assertSame(1, $result->id);
        $this->assertTrue($result->isActive);
    }
}
