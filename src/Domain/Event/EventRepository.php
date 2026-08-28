<?php

namespace QOR\App\Domain\Event;

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
     * All events (any status) created by a given organizer, most-recent-start-first.
     * Used by the admin "my events" listing (T41).
     *
     * @return list<Event>
     */
    public function findByCreator(EventCreatedByType $createdByType, int $createdById): array;

    public function save(Event $event): Event;

    public function delete(int $id): void;
}
