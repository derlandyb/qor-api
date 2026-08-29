<?php

namespace Tests\Unit\Domain\Billing\UseCase;

use DateTimeImmutable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Billing\Enum\SubscribableType;
use QOR\App\Domain\Billing\Enum\SubscriptionStatus;
use QOR\App\Domain\Billing\Plan;
use QOR\App\Domain\Billing\PlanRepository;
use QOR\App\Domain\Billing\Subscription;
use QOR\App\Domain\Billing\SubscriptionRepository;
use QOR\App\Domain\Billing\UseCase\GetOrganizerUsage;

class GetOrganizerUsageTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function makeSubscription(int $publishesUsedThisPeriod): Subscription
    {
        return new Subscription(
            id: 1,
            subscribableType: SubscribableType::Venue,
            subscribableId: 10,
            planId: 1,
            status: SubscriptionStatus::Active,
            currentPeriodStart: new DateTimeImmutable('first day of this month'),
            currentPeriodEnd: new DateTimeImmutable('first day of next month'),
            publishesUsedThisPeriod: $publishesUsedThisPeriod,
        );
    }

    private function makePlan(): Plan
    {
        return new Plan(
            id: 1,
            name: 'Básico',
            monthlyPrice: 49.9,
            annualPrice: null,
            publishQuota: 5,
        );
    }

    public function test_GIVEN_3_of_5_publishes_used_WHEN_getting_usage_THEN_it_reports_3_of_5_and_not_at_limit(): void
    {
        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('findBySubscribable')->once()->with(SubscribableType::Venue, 10)->andReturn($this->makeSubscription(3));

        $planRepository = Mockery::mock(PlanRepository::class);
        $planRepository->shouldReceive('findById')->once()->with(1)->andReturn($this->makePlan());

        $useCase = new GetOrganizerUsage($subscriptionRepository, $planRepository);

        $result = $useCase->execute(SubscribableType::Venue, 10);

        $this->assertSame('Básico', $result->planName);
        $this->assertSame(5, $result->publishQuota);
        $this->assertSame(3, $result->publishesUsedThisPeriod);
        $this->assertFalse($result->isAtLimit);
    }

    public function test_GIVEN_5_of_5_publishes_used_WHEN_getting_usage_THEN_it_flags_at_limit(): void
    {
        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('findBySubscribable')->once()->with(SubscribableType::Venue, 10)->andReturn($this->makeSubscription(5));

        $planRepository = Mockery::mock(PlanRepository::class);
        $planRepository->shouldReceive('findById')->once()->with(1)->andReturn($this->makePlan());

        $useCase = new GetOrganizerUsage($subscriptionRepository, $planRepository);

        $result = $useCase->execute(SubscribableType::Venue, 10);

        $this->assertTrue($result->isAtLimit);
    }
}
