<?php

namespace Tests\Unit\Domain\Billing\UseCase;

use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Billing\Plan;
use QOR\App\Domain\Billing\PlanRepository;
use QOR\App\Domain\Billing\UseCase\UpdatePlan;

class UpdatePlanTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_GIVEN_an_existing_plan_WHEN_updating_its_quota_THEN_existing_subscribers_usage_counts_are_unchanged(): void
    {
        $existingPlan = new Plan(
            id: 1,
            name: 'Básico',
            monthlyPrice: 49.9,
            annualPrice: null,
            publishQuota: 5,
        );

        $repository = Mockery::mock(PlanRepository::class);
        $repository->shouldReceive('findById')->once()->with(1)->andReturn($existingPlan);
        $repository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Plan $plan) => $plan->id === 1 && $plan->publishQuota === 10))
            ->andReturnUsing(fn (Plan $plan) => $plan);

        $useCase = new UpdatePlan($repository);

        $result = $useCase->execute(1, 'Básico', 49.9, 10);

        $this->assertSame(10, $result->publishQuota);
    }

    public function test_GIVEN_an_unknown_plan_id_WHEN_updating_THEN_it_rejects_with_a_generic_message(): void
    {
        $repository = Mockery::mock(PlanRepository::class);
        $repository->shouldReceive('findById')->once()->with(999)->andReturn(null);
        $repository->shouldNotReceive('save');

        $useCase = new UpdatePlan($repository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Plano não encontrado.');

        $useCase->execute(999, 'Básico', 49.9, 10);
    }
}
