<?php

namespace QOR\App\Infrastructure\Persistence\Eloquent;

use Database\Factories\FavoriteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \Illuminate\Support\Carbon $created_at
 */
class FavoriteModel extends Model
{
    /** @use HasFactory<FavoriteFactory> */
    use HasFactory;

    protected $table = 'favorites';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'event_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    protected static function newFactory(): FavoriteFactory
    {
        return FavoriteFactory::new();
    }

    /**
     * @return BelongsTo<EventModel, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(EventModel::class, 'event_id');
    }
}
