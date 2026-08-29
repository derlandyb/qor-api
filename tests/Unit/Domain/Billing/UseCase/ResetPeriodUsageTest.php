<?php

namespace Tests\Unit\Domain\Billing\UseCase;

use DateTimeImmutable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Billing\Enum\SubscribableType;
use QOR\App\Domain\Billing\Enum\SubscriptionStatus;
use QOR\App\Domain\Billing\Subscription;
use QOR\App\Domain\Billing\SubscriptionRepository;
use QOR\App\Domain\Billing\UseCase\ResetPeriodUsage;

class ResetPeriodUsageTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function makeSubscription(int $id, int $subscribableId, int $publishesUsedThisPeriod, string $periodStart): Subscription
    {
        return new Subscription(
            id: $id,
            subscribableType: SubscribableType::Venue,
            subscribableId: $subscribableId,
            planId: 1,
            status: SubscriptionStatus::Active,
            currentPeriodStart: new DateTimeImmutable($periodStart),
            currentPeriodEnd: new DateTimeImmutable($periodStart . ' +1 month'),
            publishesUsedThisPeriod: $publishesUsedThisPeriod,
        );
    }

    public function test_GIVEN_subscriptions_at_full_usage_WHEN_resetting_THEN_every_usage_count_becomes_zero(): void
    {
        $subscription1 = $this->makeSubscription(1, 10, 5, '2026-08-01');
        $subscription2 = $this->makeSubscription(2, 20, 3, '2026-08-01');

        $repository = Mockery::mock(SubscriptionRepository::class);
        $repository->shouldReceive('all')->once()->andReturn([$subscription1, $subscription2]);
        $repository->shouldReceive('save')
            ->twice()
            ->with(Mockery::on(fn (Subscription $s) => $s->publishesUsedThisPeriod === 0))
            ->andReturnUsing(fn (Subscription $s) => $s);

        $useCase = new ResetPeriodUsage($repository);

        $useCase->execute();

        $this->assertTrue(true);
    }

    public function test_GIVEN_subscriptions_with_different_signup_dates_WHEN_resetting_THEN_all_are_reset_regardless_of_signup_date(): void
    {
        $subscription1 = $this->makeSubscription(1, 10, 2, '2026-01-15');
        $subscription2 = $this->makeSubscription(2, 20, 1, '2026-08-27');

        $repository = Mockery::mock(SubscriptionRepository::class);
        $repository->shouldReceive('all')->once()->andReturn([$subscription1, $subscription2]);
        $repository->shouldReceive('save')
            ->twice()
            ->with(Mockery::on(fn (Subscription $s) => $s->publishesUsedThisPeriod === 0))
            ->andReturnUsing(fn (Subscription $s) => $s);

        $useCase = new ResetPeriodUsage($repository);

        $useCase->execute();

        $this->assertTrue(true);
    }
}
