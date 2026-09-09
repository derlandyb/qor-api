<?php

namespace Tests\Unit\Domain\Event;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Event\MapBounds;

class MapBoundsTest extends TestCase
{
    public function test_GIVEN_a_valid_box_WHEN_constructing_THEN_it_succeeds(): void
    {
        $bounds = new MapBounds(north: -20.0, south: -21.0, east: -40.0, west: -41.0);

        $this->assertSame(-20.0, $bounds->north);
        $this->assertSame(-21.0, $bounds->south);
        $this->assertSame(-40.0, $bounds->east);
        $this->assertSame(-41.0, $bounds->west);
    }

    public function test_GIVEN_north_not_greater_than_south_WHEN_constructing_THEN_it_rejects(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MapBounds(north: -21.0, south: -21.0, east: -40.0, west: -41.0);
    }

    public function test_GIVEN_east_not_greater_than_west_WHEN_constructing_THEN_it_rejects(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MapBounds(north: -20.0, south: -21.0, east: -41.0, west: -41.0);
    }
}
