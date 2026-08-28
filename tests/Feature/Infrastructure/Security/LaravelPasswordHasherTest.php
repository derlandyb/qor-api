<?php

namespace Tests\Feature\Infrastructure\Security;

use QOR\App\Infrastructure\Security\LaravelPasswordHasher;
use Tests\TestCase;

class LaravelPasswordHasherTest extends TestCase
{
    public function test_GIVEN_a_plain_password_WHEN_hashing_THEN_it_verifies_against_the_original(): void
    {
        $hasher = new LaravelPasswordHasher();

        $hash = $hasher->hash('Senha123');

        $this->assertNotSame('Senha123', $hash);
        $this->assertTrue($hasher->verify('Senha123', $hash));
    }

    public function test_GIVEN_a_hash_WHEN_verifying_the_wrong_password_THEN_it_fails(): void
    {
        $hasher = new LaravelPasswordHasher();

        $hash = $hasher->hash('Senha123');

        $this->assertFalse($hasher->verify('SenhaErrada1', $hash));
    }
}
