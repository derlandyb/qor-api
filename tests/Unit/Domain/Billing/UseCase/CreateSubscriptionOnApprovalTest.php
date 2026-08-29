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
use QOR\App\Domain\Billing\UseCase\CreateSubscriptionOnApproval;

class CreateSubscriptionOnApprovalTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function makeDefaultFreePlan(): Plan
    {
        return new Plan(
            id: 1,
            name: 'Gratuito',
            monthlyPrice: 0.0,
            annualPrice: null,
            publishQuota: 5,
            isActive: true,
            isDefaultFree: true,
        );
    }

    public function test_GIVEN_a_default_free_plan_exists_WHEN_creating_a_subscription_on_approval_THEN_it_is_created_on_that_plan_with_zero_usage(): void
    {
        $plan = $this->makeDefaultFreePlan();

        $planRepository = Mockery::mock(PlanRepository::class);
        $planRepository->shouldReceive('findDefaultFree')->once()->andReturn($plan);

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Subscription $s) => $s->subscribableType === SubscribableType::Venue
                && $s->subscribableId === 10
                && $s->planId === 1
                && $s->status === SubscriptionStatus::Active
                && $s->publishesUsedThisPeriod === 0
                && $s->currentPeriodStart instanceof DateTimeImmutable
                && $s->currentPeriodEnd instanceof DateTimeImmutable
                && $s->currentPeriodStart < $s->currentPeriodEnd))
            ->andReturnUsing(fn (Subscription $s) => new Subscription(
                id: 100,
                subscribableType: $s->subscribableType,
                subscribableId: $s->subscribableId,
                planId: $s->planId,
                status: $s->status,
                currentPeriodStart: $s->currentPeriodStart,
                currentPeriodEnd: $s->currentPeriodEnd,
                publishesUsedThisPeriod: $s->publishesUsedThisPeriod,
            ));

        $useCase = new CreateSubscriptionOnApproval($planRepository, $subscriptionRepository);

        $result = $useCase->execute(SubscribableType::Venue, 10);

        $this->assertSame(100, $result->id);
        $this->assertSame(0, $result->publishesUsedThisPeriod);
        $this->assertSame(SubscriptionStatus::Active, $result->status);
    }

    public function test_GIVEN_no_plan_is_flagged_default_free_WHEN_creating_a_subscription_on_approval_THEN_it_throws_a_configuration_error(): void
    {
        $planRepository = Mockery::mock(PlanRepository::class);
        $planRepository->shouldReceive('findDefaultFree')->once()->andReturn(null);

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldNotReceive('save');

        $useCase = new CreateSubscriptionOnApproval($planRepository, $subscriptionRepository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Configure um plano gratuito padrão antes de aprovar contas.');

        $useCase->execute(SubscribableType::Venue, 10);
    }

    public function test_GIVEN_a_promoter_account_WHEN_creating_a_subscription_on_approval_THEN_the_subscribable_type_is_promoter(): void
    {
        $plan = $this->makeDefaultFreePlan();

        $planRepository = Mockery::mock(PlanRepository::class);
        $planRepository->shouldReceive('findDefaultFree')->once()->andReturn($plan);

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Subscription $s) => $s->subscribableType === SubscribableType::Promoter
                && $s->subscribableId === 20))
            ->andReturnUsing(fn (Subscription $s) => $s);

        $useCase = new CreateSubscriptionOnApproval($planRepository, $subscriptionRepository);

        $result = $useCase->execute(SubscribableType::Promoter, 20);

        $this->assertSame(SubscribableType::Promoter, $result->subscribableType);
    }
}
