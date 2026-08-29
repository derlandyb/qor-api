<?php

namespace Tests\Unit\Domain\Billing\UseCase;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Billing\Plan;
use QOR\App\Domain\Billing\PlanRepository;
use QOR\App\Domain\Billing\UseCase\ListAllPlans;

class ListAllPlansTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function makePlan(int $id, string $name, bool $isActive = true): Plan
    {
        return new Plan(
            id: $id,
            name: $name,
            monthlyPrice: 49.9,
            annualPrice: null,
            publishQuota: 5,
            isActive: $isActive,
        );
    }

    public function test_GIVEN_active_and_inactive_plans_WHEN_listing_all_THEN_both_are_returned(): void
    {
        $active = $this->makePlan(1, 'Básico');
        $inactive = $this->makePlan(2, 'Descontinuado', isActive: false);

        $repository = Mockery::mock(PlanRepository::class);
        $repository->shouldReceive('findAll')->once()->andReturn([$active, $inactive]);

        $useCase = new ListAllPlans($repository);

        $result = $useCase->execute();

        $this->assertSame([$active, $inactive], $result);
        $this->assertCount(2, $result);
    }

    public function test_GIVEN_no_plans_exist_WHEN_listing_all_THEN_an_empty_array_is_returned(): void
    {
        $repository = Mockery::mock(PlanRepository::class);
        $repository->shouldReceive('findAll')->once()->andReturn([]);

        $useCase = new ListAllPlans($repository);

        $this->assertSame([], $useCase->execute());
    }
}
