<?php

namespace QOR\App\Domain\Billing;

use QOR\App\Domain\Billing\Enum\SubscribableType;

interface SubscriptionRepository
{
    public function findBySubscribable(SubscribableType $type, int $subscribableId): ?Subscription;

    public function save(Subscription $subscription): Subscription;

    /**
     * Atomically checks and increments a subscription's usage counter under
     * a single row lock, so two concurrent calls for the same subscribable
     * cannot both observe "under quota" and both increment. Returns false
     * without writing when the plan's quota has already been reached.
     */
    public function incrementUsageIfUnderQuota(SubscribableType $type, int $subscribableId): bool;

    /**
     * @return list<Subscription>
     */
    public function all(): array;
}
