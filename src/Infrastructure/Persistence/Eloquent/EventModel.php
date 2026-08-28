<?php

namespace QOR\App\Infrastructure\Persistence\Eloquent;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Shared\Enum\City;

/**
 * @property-read EventCreatedByType $created_by_type
 * @property-read City $city
 * @property-read EventStatus $status
 * @property-read \Illuminate\Support\Carbon $starts_at
 */
class EventModel extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected $table = 'events';

    protected $fillable = [
        'created_by_type',
        'created_by_id',
        'title',
        'description',
        'cover_image_url',
        'starts_at',
        'city',
        'genre_id',
        'address',
        'is_free',
        'ticket_url',
        'capacity',
        'age_rating',
        'notes',
        'status',
        'rejection_feedback',
    ];

    protected function casts(): array
    {
        return [
            'created_by_type' => EventCreatedByType::class,
            'city' => City::class,
            'status' => EventStatus::class,
            'starts_at' => 'datetime',
            'is_free' => 'boolean',
        ];
    }

    protected static function newFactory(): EventFactory
    {
        return EventFactory::new();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<PromoterModel, $this>
     */
    public function promoters(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(PromoterModel::class, 'event_promoter', 'event_id', 'promoter_id')
            ->withPivot('tagged_at');
    }
}
