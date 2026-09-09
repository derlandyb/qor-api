<?php

namespace QOR\App\Infrastructure\Geocoding;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use QOR\App\Domain\Event\Coordinates;
use QOR\App\Domain\Event\GeocodingPort;
use Throwable;

class GoogleGeocodingAdapter implements GeocodingPort
{
    public function geocode(string $address): ?Coordinates
    {
        /** @var string $apiKey */
        $apiKey = config('qor.geocoding.google.api_key');
        /** @var string $endpoint */
        $endpoint = config('qor.geocoding.google.endpoint');

        // Provider failures (no results, API error, timeout) never throw —
        // callers persist the event without coordinates and log the failure
        // (MAPGEO-02), same non-blocking discipline as NotificationSender.
        try {
            $response = Http::get($endpoint, [
                'address' => $address,
                'key' => $apiKey,
            ]);

            if ($response->failed()) {
                Log::error('Falha ao geocodificar endereço: requisição à Google Geocoding API falhou.', [
                    'address' => $address,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $body = $response->json();

            if (! is_array($body) || ($body['status'] ?? null) !== 'OK') {
                Log::error('Falha ao geocodificar endereço: nenhum resultado retornado pela Google Geocoding API.', [
                    'address' => $address,
                    'status' => is_array($body) ? ($body['status'] ?? null) : null,
                ]);

                return null;
            }

            $location = $body['results'][0]['geometry']['location'] ?? null;

            if (! is_array($location) || ! isset($location['lat'], $location['lng'])) {
                Log::error('Falha ao geocodificar endereço: resposta da Google Geocoding API sem coordenadas.', [
                    'address' => $address,
                ]);

                return null;
            }

            return new Coordinates((float) $location['lat'], (float) $location['lng']);
        } catch (Throwable $e) {
            Log::error('Falha ao geocodificar endereço: exceção ao chamar a Google Geocoding API.', [
                'address' => $address,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
