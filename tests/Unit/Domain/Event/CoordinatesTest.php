<?php

namespace Tests\Unit\Domain\Event;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Event\Coordinates;

class CoordinatesTest extends TestCase
{
    public function test_GIVEN_valid_lat_lng_WHEN_constructing_THEN_it_succeeds(): void
    {
        $coordinates = new Coordinates(-20.3155, -40.3128);

        $this->assertSame(-20.3155, $coordinates->latitude);
        $this->assertSame(-40.3128, $coordinates->longitude);
    }

    public function test_GIVEN_boundary_lat_lng_values_WHEN_constructing_THEN_it_succeeds(): void
    {
        $coordinates = new Coordinates(90.0, 180.0);

        $this->assertSame(90.0, $coordinates->latitude);
        $this->assertSame(180.0, $coordinates->longitude);
    }

    public function test_GIVEN_a_latitude_above_90_WHEN_constructing_THEN_it_rejects(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Coordinates(90.1, 0.0);
    }

    public function test_GIVEN_a_latitude_below_negative_90_WHEN_constructing_THEN_it_rejects(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Coordinates(-90.1, 0.0);
    }

    public function test_GIVEN_a_longitude_above_180_WHEN_constructing_THEN_it_rejects(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Coordinates(0.0, 180.1);
    }

    public function test_GIVEN_a_longitude_below_negative_180_WHEN_constructing_THEN_it_rejects(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Coordinates(0.0, -180.1);
    }
}
