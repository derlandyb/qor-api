<?php

namespace Tests\Unit\Domain\Billing\UseCase;

use DateTimeImmutable;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Billing\Enum\SubscribableType;
use QOR\App\Domain\Billing\Enum\SubscriptionStatus;
use QOR\App\Domain\Billing\Plan;
use QOR\App\Domain\Billing\PlanRepository;
use QOR\App\Domain\Billing\Subscription;
use QOR\App\Domain\Billing\SubscriptionRepository;
use QOR\App\Domain\Billing\UseCase\CheckAndIncrementQuota;

class CheckAndIncrementQuotaTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function makePlan(?int $publishQuota): Plan
    {
        return new Plan(
            id: 1,
            name: 'Básico',
            monthlyPrice: 49.9,
            annualPrice: null,
            publishQuota: $publishQuota,
        );
    }

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

    public function test_GIVEN_usage_below_quota_WHEN_checking_and_incrementing_THEN_the_count_increments_by_one(): void
    {
        $subscription = $this->makeSubscription(2);
        $plan = $this->makePlan(5);

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('findBySubscribable')->once()->with(SubscribableType::Venue, 10)->andReturn($subscription);
        $subscriptionRepository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Subscription $s) => $s->publishesUsedThisPeriod === 3))
            ->andReturnUsing(fn (Subscription $s) => $s);

        $planRepository = Mockery::mock(PlanRepository::class);
        $planRepository->shouldReceive('findById')->once()->with(1)->andReturn($plan);

        $useCase = new CheckAndIncrementQuota($subscriptionRepository, $planRepository);

        $useCase->execute(SubscribableType::Venue, 10);

        $this->assertTrue(true);
    }

    public function test_GIVEN_usage_at_quota_WHEN_checking_and_incrementing_THEN_it_blocks_and_the_count_is_unchanged(): void
    {
        $subscription = $this->makeSubscription(5);
        $plan = $this->makePlan(5);

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('findBySubscribable')->once()->with(SubscribableType::Venue, 10)->andReturn($subscription);
        $subscriptionRepository->shouldNotReceive('save');

        $planRepository = Mockery::mock(PlanRepository::class);
        $planRepository->shouldReceive('findById')->once()->with(1)->andReturn($plan);

        $useCase = new CheckAndIncrementQuota($subscriptionRepository, $planRepository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Você atingiu o limite de publicações do seu plano.');

        $useCase->execute(SubscribableType::Venue, 10);
    }

    public function test_GIVEN_an_unlimited_quota_plan_WHEN_checking_and_incrementing_THEN_it_never_blocks_regardless_of_usage(): void
    {
        $subscription = $this->makeSubscription(9999);
        $plan = $this->makePlan(null);

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('findBySubscribable')->once()->with(SubscribableType::Venue, 10)->andReturn($subscription);
        $subscriptionRepository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Subscription $s) => $s->publishesUsedThisPeriod === 10000))
            ->andReturnUsing(fn (Subscription $s) => $s);

        $planRepository = Mockery::mock(PlanRepository::class);
        $planRepository->shouldReceive('findById')->once()->with(1)->andReturn($plan);

        $useCase = new CheckAndIncrementQuota($subscriptionRepository, $planRepository);

        $useCase->execute(SubscribableType::Venue, 10);

        $this->assertTrue(true);
    }

    public function test_GIVEN_usage_exactly_one_below_quota_WHEN_checking_and_incrementing_twice_THEN_the_second_call_blocks(): void
    {
        $firstSubscription = $this->makeSubscription(4);
        $secondSubscription = $this->makeSubscription(5);
        $plan = $this->makePlan(5);

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('findBySubscribable')->once()->with(SubscribableType::Venue, 10)->andReturn($firstSubscription);
        $subscriptionRepository->shouldReceive('findBySubscribable')->once()->with(SubscribableType::Venue, 10)->andReturn($secondSubscription);
        $subscriptionRepository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Subscription $s) => $s->publishesUsedThisPeriod === 5))
            ->andReturnUsing(fn (Subscription $s) => $s);

        $planRepository = Mockery::mock(PlanRepository::class);
        $planRepository->shouldReceive('findById')->twice()->with(1)->andReturn($plan);

        $useCase = new CheckAndIncrementQuota($subscriptionRepository, $planRepository);

        $useCase->execute(SubscribableType::Venue, 10);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Você atingiu o limite de publicações do seu plano.');

        $useCase->execute(SubscribableType::Venue, 10);
    }
}
