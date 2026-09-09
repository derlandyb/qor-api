<?php

namespace QOR\App\Domain\Event;

interface GeocodingPort
{
    /**
     * Resolves a free-text address to its coordinates. Returns null on any
     * failure (no results, provider error, timeout) rather than throwing, so
     * every call site can save the event without coordinates and log the
     * failure (MAPGEO-02) without wrapping every call in a try/catch.
     */
    public function geocode(string $address): ?Coordinates;
}
