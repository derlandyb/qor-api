<?php

namespace Tests\Feature\Infrastructure\Geocoding;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use QOR\App\Infrastructure\Geocoding\GoogleGeocodingAdapter;
use Tests\TestCase;

class GoogleGeocodingAdapterTest extends TestCase
{
    public function test_GIVEN_a_resolvable_address_WHEN_geocoding_THEN_it_returns_coordinates(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'results' => [
                    [
                        'geometry' => [
                            'location' => ['lat' => -20.3155, 'lng' => -40.3128],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $adapter = new GoogleGeocodingAdapter();

        $coordinates = $adapter->geocode('Rua das Flores, 123, Vitória, ES');

        $this->assertNotNull($coordinates);
        $this->assertSame(-20.3155, $coordinates->latitude);
        $this->assertSame(-40.3128, $coordinates->longitude);
    }

    public function test_GIVEN_an_unresolvable_address_WHEN_geocoding_THEN_it_returns_null_and_logs(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response(['status' => 'ZERO_RESULTS', 'results' => []], 200),
        ]);
        Log::spy();

        $adapter = new GoogleGeocodingAdapter();

        $coordinates = $adapter->geocode('endereço que não existe em lugar nenhum');

        $this->assertNull($coordinates);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_GIVEN_an_api_error_response_WHEN_geocoding_THEN_it_returns_null_and_logs(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response(['error_message' => 'Invalid request'], 500),
        ]);
        Log::spy();

        $adapter = new GoogleGeocodingAdapter();

        $coordinates = $adapter->geocode('Rua das Flores, 123');

        $this->assertNull($coordinates);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_GIVEN_a_timeout_or_connection_exception_WHEN_geocoding_THEN_it_returns_null_and_logs_without_throwing(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        });
        Log::spy();

        $adapter = new GoogleGeocodingAdapter();

        $coordinates = $adapter->geocode('Rua das Flores, 123');

        $this->assertNull($coordinates);
        Log::shouldHaveReceived('error')->once();
    }
}
