<?php

namespace Tests\Unit\Domain\User\Enum;

use PHPUnit\Framework\TestCase;
use QOR\App\Domain\User\Enum\ConsentableType;
use ValueError;

class ConsentableTypeTest extends TestCase
{
    public function test_GIVEN_a_valid_raw_value_WHEN_cast_THEN_it_resolves_to_the_correct_case(): void
    {
        $this->assertSame(ConsentableType::User, ConsentableType::from('user'));
        $this->assertSame(ConsentableType::AdminUser, ConsentableType::from('admin_user'));
    }

    public function test_GIVEN_an_invalid_raw_value_WHEN_cast_THEN_it_throws(): void
    {
        $this->expectException(ValueError::class);

        ConsentableType::from('not_a_type');
    }
}
