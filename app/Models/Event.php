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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class Event extends Model
{
    use HasFactory;

    /** status values a first-time submission (never yet Published) can still be directly edited from. */
    private const RESUBMITTABLE_STATUSES = [EventStatus::Draft, EventStatus::PendingApproval, EventStatus::ChangesRequested];

    protected $fillable = [
        'title', 'description', 'cover_image_url', 'start_date_time', 'end_date_time',
        'venue_id', 'city', 'price_min', 'price_max', 'is_free', 'currency',
        'age_rating', 'genres', 'ticket_url', 'status', 'reviewer_feedback',
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

    /** At most one live row per event — enforced by event_revisions' unique event_id (PUBLISH-002 AC3). */
    public function pendingRevision(): HasOne
    {
        return $this->hasOne(EventRevision::class);
    }

    /** PUBLISH-001: `status` becomes Draft if `$submit` is false, PendingApproval if true. */
    public static function submitForApproval(array $fields, bool $submit): self
    {
        return self::query()->create([
            ...$fields,
            'status' => $submit ? EventStatus::PendingApproval : EventStatus::Draft,
        ]);
    }

    /**
     * For a first-time submission still in Draft/PendingApproval/ChangesRequested (never yet
     * Published): updates the row's own fields directly and sets `status` per `$submit`,
     * clearing `reviewer_feedback` — there is no live public version to protect yet.
     */
    public function resubmit(array $fields, bool $submit): void
    {
        abort_unless(in_array($this->status, self::RESUBMITTABLE_STATUSES, true), 409, 'Este evento não pode mais ser editado diretamente.');

        $this->update([
            ...$fields,
            'status' => $submit ? EventStatus::PendingApproval : EventStatus::Draft,
            'reviewer_feedback' => null,
        ]);
    }

    /**
     * For a Published event (PUBLISH-002/003): creates or replaces its EventRevision shadow
     * record. Never mutates the live Event row's fields — the previously-approved version
     * stays publicly visible until moderation applies the revision.
     */
    public function submitEdit(array $fields): void
    {
        abort_unless($this->status === EventStatus::Published, 409, 'Este evento não pode receber uma edição neste momento.');

        EventRevision::submitFor($this, $fields);
    }

    /**
     * PUBLISH-005/006/007: immediate effect, no approval step, and — the direct
     * consequence of "immediate" — discards any in-flight edit in the same transaction,
     * since there is nothing left for that edit to be approved onto.
     */
    public function cancel(): void
    {
        abort_if($this->status === EventStatus::Cancelled, 409, 'Este evento já está cancelado.');

        DB::transaction(function () {
            $this->update(['status' => EventStatus::Cancelled]);
            $this->pendingRevision()->delete(); // PUBLISH-007 — no-op if none exists
        });
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

    /** MAP edge case: omit events whose venue has no coordinates, or coordinates outside valid ranges. */
    public function scopeHasVenueCoordinates(Builder $query): Builder
    {
        return $query->whereHas('venue', function (Builder $v) {
            $v->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->whereBetween('latitude', [-90, 90])
                ->whereBetween('longitude', [-180, 180]);
        });
    }

    /** FILTER-003/004 AC1: option lists reflect strictly Published (not Cancelled) upcoming events. */
    public function scopeStrictlyPublishedUpcoming(Builder $query): Builder
    {
        return $query->where('status', EventStatus::Published)->where('start_date_time', '>', now());
    }

    /** PUBLISH-004: the publisher dashboard's "my events" read scope — a Promoter's or Venue account's own rows. */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        $promoterId = $user->promoterProfile?->id;
        $venueId = $user->venueProfile?->id;

        return $query->where(function (Builder $q) use ($promoterId, $venueId) {
            if ($promoterId !== null) {
                $q->orWhereHas('promoters', fn (Builder $p) => $p->whereKey($promoterId));
            }
            if ($venueId !== null) {
                $q->orWhere('venue_id', $venueId);
            }
        });
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
