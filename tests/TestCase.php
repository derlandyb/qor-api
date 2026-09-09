<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No test should ever make a real outbound HTTP call (e.g. via
        // GoogleGeocodingAdapter, reached indirectly by CreateEvent/
        // EditEvent). A stray request throws instead of hitting the network
        // — GeocodingPort implementations already treat any exception as a
        // non-blocking geocoding failure, so this stays safe by default for
        // every test that doesn't explicitly Http::fake().
        Http::preventStrayRequests();
    }
}
