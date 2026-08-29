<?php

namespace QOR\App\Infrastructure\Persistence;

use QOR\App\Domain\Billing\Enum\SubscribableType;
use QOR\App\Domain\Billing\Subscription;
use QOR\App\Domain\Billing\SubscriptionRepository;
use QOR\App\Infrastructure\Persistence\Eloquent\SubscriptionModel;

class EloquentSubscriptionRepository implements SubscriptionRepository
{
    public function findBySubscribable(SubscribableType $type, int $subscribableId): ?Subscription
    {
        $model = SubscriptionModel::where('subscribable_type', $type->value)
            ->where('subscribable_id', $subscribableId)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(Subscription $subscription): Subscription
    {
        $model = $subscription->id !== null ? SubscriptionModel::findOrFail($subscription->id) : new SubscriptionModel();

        $model->fill([
            'subscribable_type' => $subscription->subscribableType->value,
            'subscribable_id' => $subscription->subscribableId,
            'plan_id' => $subscription->planId,
            'status' => $subscription->status->value,
            'billing_cycle' => $subscription->billingCycle?->value,
            'current_period_start' => $subscription->currentPeriodStart,
            'current_period_end' => $subscription->currentPeriodEnd,
            'publishes_used_this_period' => $subscription->publishesUsedThisPeriod,
        ]);

        $model->save();

        return $this->toDomain($model);
    }

    public function all(): array
    {
        /** @var list<Subscription> $subscriptions */
        $subscriptions = SubscriptionModel::all()
            ->map(fn (SubscriptionModel $model): Subscription => $this->toDomain($model))
            ->values()
            ->all();

        return $subscriptions;
    }

    private function toDomain(SubscriptionModel $model): Subscription
    {
        return new Subscription(
            id: $model->id,
            subscribableType: $model->subscribable_type,
            subscribableId: $model->subscribable_id,
            planId: $model->plan_id,
            status: $model->status,
            currentPeriodStart: $model->current_period_start->toDateTimeImmutable(),
            currentPeriodEnd: $model->current_period_end->toDateTimeImmutable(),
            publishesUsedThisPeriod: $model->publishes_used_this_period,
            billingCycle: $model->billing_cycle,
        );
    }
}
