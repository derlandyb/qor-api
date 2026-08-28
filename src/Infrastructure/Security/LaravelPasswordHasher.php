<?php

namespace QOR\App\Infrastructure\Security;

use Illuminate\Support\Facades\Hash;
use QOR\App\Domain\Shared\PasswordHasher;

class LaravelPasswordHasher implements PasswordHasher
{
    public function hash(string $plainPassword): string
    {
        return Hash::make($plainPassword);
    }

    public function verify(string $plainPassword, string $hash): bool
    {
        return Hash::check($plainPassword, $hash);
    }
}
