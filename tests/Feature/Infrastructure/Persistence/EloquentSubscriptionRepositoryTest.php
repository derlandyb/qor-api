<?php

namespace Tests\Feature\Infrastructure\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use QOR\App\Domain\Billing\Enum\SubscribableType;
use QOR\App\Infrastructure\Persistence\Eloquent\SubscriptionModel;
use QOR\App\Infrastructure\Persistence\EloquentSubscriptionRepository;
use Tests\TestCase;

class EloquentSubscriptionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_a_subscription_for_a_venue_WHEN_finding_by_subscribable_THEN_it_is_returned(): void
    {
        $model = SubscriptionModel::factory()->create([
            'subscribable_type' => SubscribableType::Venue->value,
            'subscribable_id' => 42,
        ]);

        $repository = new EloquentSubscriptionRepository();

        $found = $repository->findBySubscribable(SubscribableType::Venue, 42);

        $this->assertNotNull($found);
        $this->assertSame($model->id, $found->id);
        $this->assertSame(42, $found->subscribableId);
    }

    public function test_GIVEN_an_updated_usage_count_WHEN_saving_THEN_the_persisted_row_reflects_the_new_count(): void
    {
        $model = SubscriptionModel::factory()->create(['publishes_used_this_period' => 0]);
        $repository = new EloquentSubscriptionRepository();
        $subscription = $repository->findBySubscribable($model->subscribable_type, $model->subscribable_id);

        $updated = $repository->save(new \QOR\App\Domain\Billing\Subscription(
            id: $subscription->id,
            subscribableType: $subscription->subscribableType,
            subscribableId: $subscription->subscribableId,
            planId: $subscription->planId,
            status: $subscription->status,
            currentPeriodStart: $subscription->currentPeriodStart,
            currentPeriodEnd: $subscription->currentPeriodEnd,
            publishesUsedThisPeriod: 3,
        ));

        $this->assertSame(3, $updated->publishesUsedThisPeriod);
        $this->assertDatabaseHas('subscriptions', ['id' => $model->id, 'publishes_used_this_period' => 3]);
    }
}
