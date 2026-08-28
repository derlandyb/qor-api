<?php

namespace Tests\Unit\Domain\User\Enum;

use PHPUnit\Framework\TestCase;
use QOR\App\Domain\User\Enum\ConsentType;
use ValueError;

class ConsentTypeTest extends TestCase
{
    public function test_GIVEN_a_valid_raw_value_WHEN_cast_THEN_it_resolves_to_the_correct_case(): void
    {
        $this->assertSame(ConsentType::Terms, ConsentType::from('terms'));
        $this->assertSame(ConsentType::Location, ConsentType::from('location'));
    }

    public function test_GIVEN_an_invalid_raw_value_WHEN_cast_THEN_it_throws(): void
    {
        $this->expectException(ValueError::class);

        ConsentType::from('not_a_consent_type');
    }
}
