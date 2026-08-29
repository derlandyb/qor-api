<?php

namespace QOR\App\Domain\Event;

use DateTimeImmutable;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Shared\Enum\City;

interface EventRepository
{
    /**
     * Published, non-past events, soonest-first, optionally filtered by city/genre
     * (AND-combined), cursor-paginated per `config('qor.pagination.public_page_size')`.
     */
    public function findUpcoming(?City $city, ?int $genreId, ?string $cursor): EventPage;

    public function findById(int $id): ?Event;

    /**
     * Batch lookup, preserving no particular order — callers that need a specific
     * order (e.g. a favorites list) re-order client-side by the source list.
     *
     * @param  list<int>  $ids
     * @return list<Event>
     */
    public function findByIds(array $ids): array;

    /**
     * All events (any status) created by a given organizer, most-recent-start-first.
     * Used by the admin "my events" listing (T41).
     *
     * @return list<Event>
     */
    public function findByCreator(EventCreatedByType $createdByType, int $createdById): array;

    /**
     * All Published events whose starts_at has already passed. Used by the
     * events:close-ended scheduled command (ADMIN-23) to naturally end them.
     *
     * @return list<Event>
     */
    public function findPublishedPastEnd(): array;

    /**
     * Published events in the given city that became Published since $since —
     * used by DetectRegionalPublishes (NOTIF-16–19) to scope a scan window rather
     * than re-announcing every currently-Published event on every run.
     *
     * @return list<Event>
     */
    public function findRecentlyPublished(City $city, DateTimeImmutable $since): array;

    public function save(Event $event): Event;

    public function delete(int $id): void;
}
