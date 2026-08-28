<?php

namespace QOR\App\Domain\Venue;

interface VenueRepository
{
    public function findById(int $id): ?Venue;

    public function save(Venue $venue): Venue;

    public function delete(int $id): void;
}
