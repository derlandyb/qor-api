<?php

namespace Tests\Unit\Domain\Billing\UseCase;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Billing\Plan;
use QOR\App\Domain\Billing\PlanRepository;
use QOR\App\Domain\Billing\UseCase\DeactivatePlan;

class DeactivatePlanTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_GIVEN_an_active_plan_with_a_subscriber_WHEN_deactivating_THEN_the_subscribers_subscription_is_untouched(): void
    {
        $plan = new Plan(
            id: 1,
            name: 'Básico',
            monthlyPrice: 49.9,
            annualPrice: null,
            publishQuota: 5,
            isActive: true,
        );

        $repository = Mockery::mock(PlanRepository::class);
        $repository->shouldReceive('findById')->once()->with(1)->andReturn($plan);
        $repository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Plan $p) => $p->id === 1 && $p->isActive === false))
            ->andReturnUsing(fn (Plan $p) => $p);

        $useCase = new DeactivatePlan($repository);

        $result = $useCase->execute(1);

        $this->assertFalse($result->isActive);
    }
}
