<?php

namespace QOR\App\Domain\Billing;

use QOR\App\Domain\Billing\Enum\SubscribableType;

interface SubscriptionRepository
{
    public function findBySubscribable(SubscribableType $type, int $subscribableId): ?Subscription;

    public function save(Subscription $subscription): Subscription;

    /**
     * @return list<Subscription>
     */
    public function all(): array;
}
