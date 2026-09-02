<?php

namespace QOR\App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Internal storage for OtpAdapter (Infrastructure/Auth/OtpAdapter.php) — not
 * a domain entity, never exposed outside that adapter.
 *
 * @property-read \Illuminate\Support\Carbon $expires_at
 */
class OtpCodeModel extends Model
{
    protected $table = 'otp_codes';

    protected $fillable = [
        'purpose',
        'identifier',
        'code_hash',
        'attempts',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
        ];
    }
}
