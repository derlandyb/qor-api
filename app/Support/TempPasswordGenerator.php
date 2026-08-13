<?php

namespace App\Support;

use Illuminate\Support\Str;

final class TempPasswordGenerator
{
    /**
     * A server-generated temp password for staff-account creation — satisfies
     * RegisterRequest::passwordStrengthRule() (8+ chars, letters + numbers) by construction,
     * since Str::password() always mixes letters, numbers, and symbols.
     */
    public static function generate(): string
    {
        return Str::password(16);
    }
}
