<?php

namespace QOR\App\Domain\Promoter;

interface PromoterRepository
{
    public function findById(int $id): ?Promoter;

    /**
     * Promoters tagged on an event via the EventPromoter join (display-only, no edit rights).
     *
     * @return list<Promoter>
     */
    public function findTaggedForEvent(int $eventId): array;

    public function save(Promoter $promoter): Promoter;

    public function delete(int $id): void;
}
