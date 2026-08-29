<?php

namespace Tests\Unit\Domain\Billing\UseCase;

use DateTimeImmutable;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Billing\Enum\SubscribableType;
use QOR\App\Domain\Billing\Enum\SubscriptionStatus;
use QOR\App\Domain\Billing\Exception\QuotaExceeded;
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

    public function test_GIVEN_usage_below_quota_WHEN_checking_and_incrementing_THEN_the_repository_is_asked_to_increment(): void
    {
        $subscription = $this->makeSubscription(2);
        $plan = $this->makePlan(5);

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('findBySubscribable')->once()->with(SubscribableType::Venue, 10)->andReturn($subscription);
        $subscriptionRepository->shouldReceive('incrementUsageIfUnderQuota')->once()->with(SubscribableType::Venue, 10)->andReturn(true);

        $planRepository = Mockery::mock(PlanRepository::class);
        $planRepository->shouldReceive('findById')->once()->with(1)->andReturn($plan);

        $useCase = new CheckAndIncrementQuota($subscriptionRepository, $planRepository);

        $useCase->execute(SubscribableType::Venue, 10);

        $this->assertTrue(true);
    }

    public function test_GIVEN_the_repository_reports_the_quota_is_reached_WHEN_checking_and_incrementing_THEN_it_throws_quota_exceeded(): void
    {
        $subscription = $this->makeSubscription(5);
        $plan = $this->makePlan(5);

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('findBySubscribable')->once()->with(SubscribableType::Venue, 10)->andReturn($subscription);
        $subscriptionRepository->shouldReceive('incrementUsageIfUnderQuota')->once()->with(SubscribableType::Venue, 10)->andReturn(false);

        $planRepository = Mockery::mock(PlanRepository::class);
        $planRepository->shouldReceive('findById')->once()->with(1)->andReturn($plan);

        $useCase = new CheckAndIncrementQuota($subscriptionRepository, $planRepository);

        $this->expectException(QuotaExceeded::class);
        $this->expectExceptionMessage('Você atingiu o limite de publicações do seu plano.');

        $useCase->execute(SubscribableType::Venue, 10);
    }

    public function test_GIVEN_an_unlimited_quota_plan_WHEN_checking_and_incrementing_THEN_it_never_blocks_regardless_of_usage(): void
    {
        $subscription = $this->makeSubscription(9999);
        $plan = $this->makePlan(null);

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('findBySubscribable')->once()->with(SubscribableType::Venue, 10)->andReturn($subscription);
        $subscriptionRepository->shouldReceive('incrementUsageIfUnderQuota')->once()->with(SubscribableType::Venue, 10)->andReturn(true);

        $planRepository = Mockery::mock(PlanRepository::class);
        $planRepository->shouldReceive('findById')->once()->with(1)->andReturn($plan);

        $useCase = new CheckAndIncrementQuota($subscriptionRepository, $planRepository);

        $useCase->execute(SubscribableType::Venue, 10);

        $this->assertTrue(true);
    }

    public function test_GIVEN_no_subscription_for_the_account_WHEN_checking_and_incrementing_THEN_it_throws_invalid_argument_exception(): void
    {
        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('findBySubscribable')->once()->with(SubscribableType::Venue, 10)->andReturn(null);
        $subscriptionRepository->shouldNotReceive('incrementUsageIfUnderQuota');

        $planRepository = Mockery::mock(PlanRepository::class);
        $planRepository->shouldNotReceive('findById');

        $useCase = new CheckAndIncrementQuota($subscriptionRepository, $planRepository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nenhuma assinatura encontrada para esta conta.');

        $useCase->execute(SubscribableType::Venue, 10);
    }

    public function test_GIVEN_the_subscriptions_plan_no_longer_exists_WHEN_checking_and_incrementing_THEN_it_throws_invalid_argument_exception(): void
    {
        $subscription = $this->makeSubscription(2);

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('findBySubscribable')->once()->with(SubscribableType::Venue, 10)->andReturn($subscription);
        $subscriptionRepository->shouldNotReceive('incrementUsageIfUnderQuota');

        $planRepository = Mockery::mock(PlanRepository::class);
        $planRepository->shouldReceive('findById')->once()->with(1)->andReturn(null);

        $useCase = new CheckAndIncrementQuota($subscriptionRepository, $planRepository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Plano da assinatura não encontrado.');

        $useCase->execute(SubscribableType::Venue, 10);
    }
}
