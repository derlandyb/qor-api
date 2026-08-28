<?php

namespace Tests\Unit\Domain\Shared\Enum;

use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Shared\Enum\City;
use ValueError;

class CityTest extends TestCase
{
    public function test_GIVEN_a_valid_raw_value_WHEN_cast_THEN_it_resolves_to_the_correct_case(): void
    {
        $this->assertSame(City::Vitoria, City::from('vitoria'));
        $this->assertSame(City::VilaVelha, City::from('vila_velha'));
        $this->assertSame(City::Serra, City::from('serra'));
        $this->assertSame(City::Cariacica, City::from('cariacica'));
    }

    public function test_GIVEN_an_invalid_raw_value_WHEN_cast_THEN_it_throws(): void
    {
        $this->expectException(ValueError::class);

        City::from('sao_paulo');
    }
}
