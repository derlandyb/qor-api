<?php

namespace App\Models;

use App\Enums\AgeRating;
use App\Enums\EventStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;

final class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'description', 'cover_image_url', 'start_date_time', 'end_date_time',
        'venue_id', 'city', 'price_min', 'price_max', 'is_free', 'currency',
        'age_rating', 'genres', 'ticket_url', 'status',
    ];

    protected $casts = [
        'start_date_time' => 'datetime',
        'end_date_time' => 'datetime',
        'status' => EventStatus::class,
        'age_rating' => AgeRating::class,
        'genres' => 'array',
        'is_free' => 'boolean',
        'price_min' => 'decimal:2',
        'price_max' => 'decimal:2',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class, 'event_artist');
    }

    public function promoters(): BelongsToMany
    {
        return $this->belongsToMany(Promoter::class, 'event_promoter');
    }

    /** The feed's core business rule: Published (or Cancelled-but-was-published) + future start. */
    public function scopePublishedUpcoming(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [EventStatus::Published, EventStatus::Cancelled])
            ->where('start_date_time', '>', now());
    }

    /** SEARCH-001..004: case/accent-insensitive substring match, AND'd across whitespace-split terms. */
    public function scopeSearch(Builder $query, string $q): Builder
    {
        $terms = array_filter(preg_split('/\s+/', trim($q)));

        foreach ($terms as $term) {
            $needle = '%'.$term.'%';
            $query->where(function (Builder $sub) use ($needle) {
                $sub->whereRaw('unaccent(title) ILIKE unaccent(?)', [$needle])
                    ->orWhereHas('venue', fn (Builder $v) => $v->whereRaw('unaccent(name) ILIKE unaccent(?)', [$needle]))
                    ->orWhereHas('artists', fn (Builder $a) => $a->whereRaw('unaccent(name) ILIKE unaccent(?)', [$needle]))
                    ->orWhereRaw(
                        'EXISTS (SELECT 1 FROM jsonb_array_elements_text(genres) g WHERE unaccent(g) ILIKE unaccent(?))',
                        [$needle]
                    );
            });
        }

        return $query;
    }

    /** SEARCH-007: rank an exact (whole-field) match above partial matches, without changing what's included. */
    public function scopeExactMatchFirst(Builder $query, string $q): Builder
    {
        return $query
            ->selectRaw('events.*, (
                unaccent(title) ILIKE unaccent(?)
                OR EXISTS (SELECT 1 FROM venues v WHERE v.id = events.venue_id AND unaccent(v.name) ILIKE unaccent(?))
                OR EXISTS (
                    SELECT 1 FROM event_artist ea JOIN artists a ON a.id = ea.artist_id
                    WHERE ea.event_id = events.id AND unaccent(a.name) ILIKE unaccent(?)
                )
                OR EXISTS (SELECT 1 FROM jsonb_array_elements_text(genres) g WHERE unaccent(g) ILIKE unaccent(?))
            ) AS is_exact_match', [$q, $q, $q, $q])
            ->orderByDesc('is_exact_match');
    }

    /** FILTER-001/007: preset date-bucket boundaries, computed against a fixed backend timezone. */
    public function scopeDateBucket(Builder $query, string $bucket): Builder
    {
        $now = Carbon::now(config('app.timezone'));

        [$start, $end] = match ($bucket) {
            'hoje' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'amanha' => [$now->copy()->addDay()->startOfDay(), $now->copy()->addDay()->endOfDay()],
            'fim_de_semana' => self::weekendRange($now),
            'proxima_semana' => self::nextWeekRange($now),
            default => throw new InvalidArgumentException("Unknown date_bucket: {$bucket}"),
        };

        return $query->whereBetween('start_date_time', [$start, $end]);
    }

    /** Always the *next* occurring Sat–Sun, or today->Sun if today already is Sat/Sun — never a past weekend. */
    private static function weekendRange(Carbon $now): array
    {
        $saturday = match (true) {
            $now->isSaturday() => $now->copy()->startOfDay(),
            $now->isSunday() => $now->copy()->subDay()->startOfDay(),
            default => $now->copy()->next(Carbon::SATURDAY)->startOfDay(),
        };

        return [$saturday, $saturday->copy()->addDay()->endOfDay()];
    }

    /** The calendar week (Mon–Sun) strictly after the current one — always skips the remainder of this week. */
    private static function nextWeekRange(Carbon $now): array
    {
        $nextMonday = $now->copy()->next(Carbon::MONDAY)->startOfDay();

        return [$nextMonday, $nextMonday->copy()->addDays(6)->endOfDay()];
    }

    /** FILTER-003/005: OR-match — any selected genre present in the event's genres jsonb array. */
    public function scopeGenres(Builder $query, array $genres): Builder
    {
        $placeholders = implode(',', array_fill(0, count($genres), '?'));

        // The jsonb "any of" operator is `?|`; doubled to `??|` because PDO would otherwise
        // interpret the bare `?` as a positional bind placeholder inside this raw fragment.
        return $query->whereRaw("genres ??| array[{$placeholders}]", $genres);
    }

    /** FILTER-003/004 AC1: option lists reflect strictly Published (not Cancelled) upcoming events. */
    public function scopeStrictlyPublishedUpcoming(Builder $query): Builder
    {
        return $query->where('status', EventStatus::Published)->where('start_date_time', '>', now());
    }

    protected static function booted(): void
    {
        // Denormalize city from venue at write time (avoids a join on every feed page).
        // Looks up by venue_id directly rather than the `venue` relation, which may hold
        // a stale cached instance when venue_id changes on an already-loaded model.
        self::saving(function (Event $event) {
            if ($event->isDirty('venue_id') || blank($event->city)) {
                $event->city = Venue::find($event->venue_id)?->city;
            }
        });
    }
}
