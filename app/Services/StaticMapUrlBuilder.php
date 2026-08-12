<?php

namespace App\Services;

final class StaticMapUrlBuilder
{
    public static function build(float $lat, float $lng): string
    {
        return sprintf(
            'https://maps.googleapis.com/maps/api/staticmap?center=%1$s,%2$s&zoom=15&size=640x280&scale=2'
            .'&markers=color:0x006d77%%7C%1$s,%2$s&key=%3$s',
            $lat,
            $lng,
            config('services.google_maps.static_key'),
        );
    }
}
