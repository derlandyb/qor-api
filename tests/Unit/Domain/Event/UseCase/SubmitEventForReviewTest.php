<?php

namespace Tests\Unit\Domain\Event\UseCase;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Domain\Billing\Enum\SubscribableType;
use QOR\App\Domain\Billing\Enum\SubscriptionStatus;
use QOR\App\Domain\Billing\Plan;
use QOR\App\Domain\Billing\PlanRepository;
use QOR\App\Domain\Billing\Subscription;
use QOR\App\Domain\Billing\SubscriptionRepository;
use QOR\App\Domain\Billing\UseCase\CheckAndIncrementQuota;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Event\UseCase\SubmitEventForReview;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\Venue\Venue;

class SubmitEventForReviewTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function approvedVenue(): Venue
    {
        return new Venue(
            id: 1,
            venueAdminUserId: 10,
            name: 'Casa de Show',
            description: 'Um lugar legal',
            address: 'Rua das Flores, 123',
            city: City::Vitoria,
            contactPhone: '27999999999',
            contactEmail: 'contato@casa.com',
            approvalStatus: ApprovalStatus::Approved,
        );
    }

    private function draftEvent(EventStatus $status = EventStatus::Draft): Event
    {
        return new Event(
            id: 5,
            createdByType: EventCreatedByType::VenueAdmin,
            createdById: 1,
            title: 'Noite Eletrônica',
            description: 'Uma noite incrível',
            startsAt: new DateTimeImmutable('+1 week'),
            city: City::Vitoria,
            genreId: 1,
            isFree: true,
            status: $status,
            address: 'Rua das Flores, 123',
        );
    }

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
            subscribableId: 1,
            planId: 1,
            status: SubscriptionStatus::Active,
            currentPeriodStart: new DateTimeImmutable('first day of this month'),
            currentPeriodEnd: new DateTimeImmutable('first day of next month'),
            publishesUsedThisPeriod: $publishesUsedThisPeriod,
        );
    }

    /**
     * Builds a real CheckAndIncrementQuota whose repositories always allow the
     * publish (usage well below quota), used by tests unrelated to quota
     * enforcement itself.
     */
    private function nonBlockingQuota(): CheckAndIncrementQuota
    {
        $subscription = $this->makeSubscription(0);
        $plan = $this->makePlan(5);

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('findBySubscribable')->with(SubscribableType::Venue, 1)->andReturn($subscription);
        $subscriptionRepository->shouldReceive('save')->andReturnUsing(fn (Subscription $s) => $s);

        $planRepository = Mockery::mock(PlanRepository::class);
        $planRepository->shouldReceive('findById')->with(1)->andReturn($plan);

        return new CheckAndIncrementQuota($subscriptionRepository, $planRepository);
    }

    public function test_GIVEN_a_draft_event_and_approved_organizer_WHEN_submitting_THEN_it_transitions_to_pending_review(): void
    {
        $venue = $this->approvedVenue();
        $event = $this->draftEvent();

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(5)->andReturn($event);
        $repository->shouldReceive('save')
            ->once()
            ->withArgs(fn (Event $e) => $e->status === EventStatus::PendingReview)
            ->andReturnUsing(fn (Event $e) => $e);

        $useCase = new SubmitEventForReview($repository, $this->nonBlockingQuota());

        $result = $useCase->execute(5, $venue);

        $this->assertSame(EventStatus::PendingReview, $result->status);
    }

    public function test_GIVEN_an_unapproved_organizer_WHEN_submitting_THEN_it_is_blocked(): void
    {
        $venue = new Venue(
            id: 1,
            venueAdminUserId: 10,
            name: 'Casa de Show',
            description: 'Um lugar legal',
            address: 'Rua das Flores, 123',
            city: City::Vitoria,
            contactPhone: '27999999999',
            contactEmail: 'contato@casa.com',
            approvalStatus: ApprovalStatus::Suspended,
        );
        $event = $this->draftEvent();

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(5)->andReturn($event);
        $repository->shouldNotReceive('save');

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldNotReceive('findBySubscribable');
        $planRepository = Mockery::mock(PlanRepository::class);
        $checkAndIncrementQuota = new CheckAndIncrementQuota($subscriptionRepository, $planRepository);

        $useCase = new SubmitEventForReview($repository, $checkAndIncrementQuota);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sua conta ainda não foi aprovada.');

        $useCase->execute(5, $venue);
    }

    public function test_GIVEN_an_event_that_does_not_exist_WHEN_submitting_THEN_it_throws(): void
    {
        $venue = $this->approvedVenue();

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(999)->andReturn(null);
        $repository->shouldNotReceive('save');

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldNotReceive('findBySubscribable');
        $planRepository = Mockery::mock(PlanRepository::class);
        $checkAndIncrementQuota = new CheckAndIncrementQuota($subscriptionRepository, $planRepository);

        $useCase = new SubmitEventForReview($repository, $checkAndIncrementQuota);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Evento não encontrado.');

        $useCase->execute(999, $venue);
    }

    public function test_GIVEN_an_event_that_cannot_legally_transition_WHEN_submitting_THEN_the_domain_exception_propagates(): void
    {
        $venue = $this->approvedVenue();
        $event = $this->draftEvent(EventStatus::Published);

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(5)->andReturn($event);
        $repository->shouldNotReceive('save');

        $useCase = new SubmitEventForReview($repository, $this->nonBlockingQuota());

        $this->expectException(DomainException::class);

        $useCase->execute(5, $venue);
    }

    public function test_GIVEN_the_organizer_is_under_quota_WHEN_submitting_THEN_the_event_transitions_and_the_quota_count_increments(): void
    {
        $venue = $this->approvedVenue();
        $event = $this->draftEvent();
        $subscription = $this->makeSubscription(2);
        $plan = $this->makePlan(5);

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(5)->andReturn($event);
        $repository->shouldReceive('save')
            ->once()
            ->withArgs(fn (Event $e) => $e->status === EventStatus::PendingReview)
            ->andReturnUsing(fn (Event $e) => $e);

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('findBySubscribable')->once()->with(SubscribableType::Venue, 1)->andReturn($subscription);
        $subscriptionRepository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Subscription $s) => $s->publishesUsedThisPeriod === 3))
            ->andReturnUsing(fn (Subscription $s) => $s);

        $planRepository = Mockery::mock(PlanRepository::class);
        $planRepository->shouldReceive('findById')->once()->with(1)->andReturn($plan);

        $useCase = new SubmitEventForReview($repository, new CheckAndIncrementQuota($subscriptionRepository, $planRepository));

        $result = $useCase->execute(5, $venue);

        $this->assertSame(EventStatus::PendingReview, $result->status);
    }

    public function test_GIVEN_the_organizer_is_at_quota_WHEN_submitting_THEN_it_is_blocked_and_the_event_stays_in_draft(): void
    {
        $venue = $this->approvedVenue();
        $event = $this->draftEvent();
        $subscription = $this->makeSubscription(5);
        $plan = $this->makePlan(5);

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(5)->andReturn($event);
        $repository->shouldNotReceive('save');

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('findBySubscribable')->once()->with(SubscribableType::Venue, 1)->andReturn($subscription);
        $subscriptionRepository->shouldNotReceive('save');

        $planRepository = Mockery::mock(PlanRepository::class);
        $planRepository->shouldReceive('findById')->once()->with(1)->andReturn($plan);

        $useCase = new SubmitEventForReview($repository, new CheckAndIncrementQuota($subscriptionRepository, $planRepository));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Você atingiu o limite de publicações do seu plano.');

        $useCase->execute(5, $venue);
    }

    public function test_GIVEN_the_organizer_is_on_an_unlimited_quota_plan_WHEN_submitting_THEN_it_never_blocks(): void
    {
        $venue = $this->approvedVenue();
        $event = $this->draftEvent();
        $subscription = $this->makeSubscription(9999);
        $plan = $this->makePlan(null);

        $repository = Mockery::mock(EventRepository::class);
        $repository->shouldReceive('findById')->once()->with(5)->andReturn($event);
        $repository->shouldReceive('save')
            ->once()
            ->withArgs(fn (Event $e) => $e->status === EventStatus::PendingReview)
            ->andReturnUsing(fn (Event $e) => $e);

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('findBySubscribable')->once()->with(SubscribableType::Venue, 1)->andReturn($subscription);
        $subscriptionRepository->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (Subscription $s) => $s->publishesUsedThisPeriod === 10000))
            ->andReturnUsing(fn (Subscription $s) => $s);

        $planRepository = Mockery::mock(PlanRepository::class);
        $planRepository->shouldReceive('findById')->once()->with(1)->andReturn($plan);

        $useCase = new SubmitEventForReview($repository, new CheckAndIncrementQuota($subscriptionRepository, $planRepository));

        $result = $useCase->execute(5, $venue);

        $this->assertSame(EventStatus::PendingReview, $result->status);
    }
}
