<?php

namespace Tests\Unit\Domain\Admin;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Admin\AdminAccount;

class AdminAccountTest extends TestCase
{
    public function test_GIVEN_valid_fields_WHEN_constructing_THEN_entity_is_valid(): void
    {
        $account = new AdminAccount(
            id: null,
            name: 'Casa de Show Vitória',
            email: 'contato@casadeshow.com',
            passwordHash: 'hashed-password',
        );

        $this->assertSame('Casa de Show Vitória', $account->name);
        $this->assertFalse($account->isSuperAdmin);
    }

    public function test_GIVEN_invalid_email_WHEN_constructing_THEN_it_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AdminAccount(id: null, name: 'Promoter X', email: 'not-an-email', passwordHash: 'hash');
    }

    public function test_GIVEN_empty_name_WHEN_constructing_THEN_it_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AdminAccount(id: null, name: '', email: 'valid@example.com', passwordHash: 'hash');
    }
}
