<?php

namespace QOR\App\Domain\Event;

use QOR\App\Domain\Shared\Enum\City;

interface EventRepository
{
    /**
     * Published, non-past events, soonest-first, optionally filtered by city/genre
     * (AND-combined), cursor-paginated per `config('qor.pagination.public_page_size')`.
     */
    public function findUpcoming(?City $city, ?int $genreId, ?string $cursor): EventPage;

    public function findById(int $id): ?Event;

    public function save(Event $event): Event;

    public function delete(int $id): void;
}
