<?php

namespace Tests\Unit\Domain\Billing\UseCase;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Billing\Plan;
use QOR\App\Domain\Billing\PlanRepository;
use QOR\App\Domain\Billing\UseCase\ListActivePlans;

class ListActivePlansTest extends TestCase
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

    public function test_GIVEN_two_active_plans_and_one_inactive_WHEN_listing_THEN_only_active_plans_are_returned(): void
    {
        $active1 = $this->makePlan(1, 'Básico');
        $active2 = $this->makePlan(2, 'Pro');

        $repository = Mockery::mock(PlanRepository::class);
        $repository->shouldReceive('findActive')->once()->andReturn([$active1, $active2]);

        $useCase = new ListActivePlans($repository);

        $result = $useCase->execute();

        $this->assertSame([$active1, $active2], $result);
    }

    public function test_GIVEN_no_active_plans_WHEN_listing_THEN_an_empty_array_is_returned(): void
    {
        $repository = Mockery::mock(PlanRepository::class);
        $repository->shouldReceive('findActive')->once()->andReturn([]);

        $useCase = new ListActivePlans($repository);

        $this->assertSame([], $useCase->execute());
    }

    public function test_GIVEN_a_plan_is_deactivated_WHEN_listing_again_THEN_it_no_longer_appears(): void
    {
        $stillActive = $this->makePlan(1, 'Básico');

        $repository = Mockery::mock(PlanRepository::class);
        $repository->shouldReceive('findActive')->once()->andReturn([$stillActive, $this->makePlan(2, 'Pro')]);
        $repository->shouldReceive('findActive')->once()->andReturn([$stillActive]);

        $useCase = new ListActivePlans($repository);

        $firstResult = $useCase->execute();
        $secondResult = $useCase->execute();

        $this->assertCount(2, $firstResult);
        $this->assertCount(1, $secondResult);
        $this->assertSame([$stillActive], $secondResult);
    }
}
