<?php

namespace QOR\App\Infrastructure\Persistence\Eloquent;

use Database\Factories\ConsentRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use QOR\App\Domain\User\Enum\ConsentableType;
use QOR\App\Domain\User\Enum\ConsentType;

/**
 * @property-read ConsentableType $consentable_type
 * @property-read ConsentType $consent_type
 * @property-read \Illuminate\Support\Carbon $accepted_at
 */
class ConsentRecordModel extends Model
{
    /** @use HasFactory<ConsentRecordFactory> */
    use HasFactory;

    protected $table = 'consent_records';

    public $timestamps = false;

    protected $fillable = [
        'consentable_type',
        'consentable_id',
        'consent_type',
        'policy_version',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'consentable_type' => ConsentableType::class,
            'consent_type' => ConsentType::class,
            'accepted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ConsentRecordFactory
    {
        return ConsentRecordFactory::new();
    }
}
