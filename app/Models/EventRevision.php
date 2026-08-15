<?php

namespace App\Models;

use App\Enums\AgeRating;
use App\Enums\EventRevisionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The pending-edit shadow record for an already-Published Event (PUBLISH-002/003).
 * A snapshot of proposed Event fields, not a diff — so moderation's eventual "apply
 * on approval" step is a plain field copy, not a patch-merge.
 */
final class EventRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'title', 'description', 'cover_image_path', 'start_date_time', 'end_date_time',
        'venue_id', 'price_min', 'price_max', 'is_free', 'currency', 'age_rating', 'genres',
        'ticket_url', 'status', 'reviewer_feedback',
    ];

    protected $casts = [
        'start_date_time' => 'datetime',
        'end_date_time' => 'datetime',
        'status' => EventRevisionStatus::class,
        'age_rating' => AgeRating::class,
        'genres' => 'array',
        'is_free' => 'boolean',
        'price_min' => 'decimal:2',
        'price_max' => 'decimal:2',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** PUBLISH-002 AC3: a second edit submitted while one is pending replaces it, not a second competing row. */
    public static function submitFor(Event $event, array $fields): self
    {
        return self::query()->updateOrCreate(
            ['event_id' => $event->id],
            [...$fields, 'status' => EventRevisionStatus::PendingApproval, 'reviewer_feedback' => null],
        );
    }
}
