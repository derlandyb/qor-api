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

    /**
     * Full-replace: removes all existing tags for the event, then tags each
     * promoter id in $promoterIds (in order) with tagged_at = now().
     *
     * @param list<int> $promoterIds
     */
    public function tagEvent(int $eventId, array $promoterIds): void;

    public function save(Promoter $promoter): Promoter;

    public function delete(int $id): void;
}
