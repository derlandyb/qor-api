<?php

namespace QOR\App\Infrastructure\Persistence;

use DateTimeImmutable;
use InvalidArgumentException;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\EventPage;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Event\MapBounds;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Infrastructure\Persistence\Eloquent\EventModel;

class EloquentEventRepository implements EventRepository
{
    /**
     * Fixed approximate center point (lat, lng) per City enum value — not
     * admin-configurable, same "fixed set of 4" spirit as the City enum
     * itself (ARCHITECTURE.md §14.1). Only `qor.map.city_radius_km` (the
     * distance around these centers) is config-driven.
     *
     * @var array<string, array{0: float, 1: float}>
     */
    private const CITY_CENTERS = [
        'vitoria' => [-20.3155, -40.3128],
        'vila_velha' => [-20.3297, -40.2925],
        'serra' => [-20.1289, -40.3078],
        'cariacica' => [-20.2632, -40.4165],
    ];

    // Approximate km per degree of latitude, used to convert the configured
    // city_radius_km into a bounding box around a city's center point —
    // consistent with Approach A's plain-bounding-box query shape, no
    // circular-radius SQL needed.
    private const KM_PER_DEGREE_LATITUDE = 111.32;

    public function findUpcoming(?City $city, ?int $genreId, ?string $cursor): EventPage
    {
        /** @var int $pageSize */
        $pageSize = config('qor.pagination.public_page_size');

        $query = EventModel::query()
            ->with('genre')
            ->where('status', EventStatus::Published->value)
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->orderBy('id');

        if ($city !== null) {
            $query->where('city', $city->value);
        }

        if ($genreId !== null) {
            $query->where('genre_id', $genreId);
        }

        if ($cursor !== null) {
            [$cursorStartsAt, $cursorId] = $this->decodeCursor($cursor);

            $query->where(function ($q) use ($cursorStartsAt, $cursorId) {
                $q->where('starts_at', '>', $cursorStartsAt)
                    ->orWhere(function ($q2) use ($cursorStartsAt, $cursorId) {
                        $q2->where('starts_at', '=', $cursorStartsAt)
                            ->where('id', '>', $cursorId);
                    });
            });
        }

        $models = $query->limit($pageSize + 1)->get();

        $hasMore = $models->count() > $pageSize;
        $models = $models->slice(0, $pageSize);

        $nextCursor = null;
        $last = $models->last();
        if ($hasMore && $last !== null) {
            $nextCursor = $this->encodeCursor($last->starts_at->toIso8601String(), $last->id);
        }

        return new EventPage(
            items: array_values($models->map(fn (EventModel $model) => $this->toDomain($model))->all()),
            nextCursor: $nextCursor,
        );
    }

    public function findById(int $id): ?Event
    {
        $model = EventModel::with('genre')->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $models = EventModel::with('genre')->whereIn('id', $ids)->get();

        return array_values($models->map(fn (EventModel $model) => $this->toDomain($model))->all());
    }

    public function findByCreator(EventCreatedByType $createdByType, int $createdById): array
    {
        $models = EventModel::with('genre')
            ->where('created_by_type', $createdByType->value)
            ->where('created_by_id', $createdById)
            ->orderByDesc('starts_at')
            ->get();

        return array_values($models->map(fn (EventModel $model) => $this->toDomain($model))->all());
    }

    public function findPublishedPastEnd(): array
    {
        $models = EventModel::with('genre')
            ->where('status', EventStatus::Published->value)
            ->where('starts_at', '<', now())
            ->get();

        return array_values($models->map(fn (EventModel $model) => $this->toDomain($model))->all());
    }

    public function findRecentlyPublished(City $city, DateTimeImmutable $since): array
    {
        // No dedicated `published_at` column exists on `events` — `updated_at` is used
        // as an approximation of "became Published" (edits also bump it, which is an
        // acceptable minor over-inclusion at v1's single-region scale; a dedicated
        // column can be added later if this proves inaccurate in practice).
        $models = EventModel::with('genre')
            ->where('status', EventStatus::Published->value)
            ->where('city', $city->value)
            ->where('updated_at', '>=', $since)
            ->get();

        return array_values($models->map(fn (EventModel $model) => $this->toDomain($model))->all());
    }

    public function findMapEvents(?MapBounds $bounds, ?City $city): array
    {
        if ($bounds === null && $city === null) {
            throw new InvalidArgumentException('É necessário informar uma área (bounds) ou uma cidade.');
        }

        $effectiveBounds = $bounds ?? $this->boundsForCity($city);

        $models = EventModel::with('genre')
            ->where('status', EventStatus::Published->value)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$effectiveBounds->south, $effectiveBounds->north])
            ->whereBetween('longitude', [$effectiveBounds->west, $effectiveBounds->east])
            ->get();

        return array_values($models->map(fn (EventModel $model) => $this->toDomain($model))->all());
    }

    private function boundsForCity(City $city): MapBounds
    {
        [$centerLatitude, $centerLongitude] = self::CITY_CENTERS[$city->value];

        /** @var float $radiusKm */
        $radiusKm = config('qor.map.city_radius_km');

        $latitudeDelta = $radiusKm / self::KM_PER_DEGREE_LATITUDE;
        $longitudeDelta = $radiusKm / (self::KM_PER_DEGREE_LATITUDE * cos(deg2rad($centerLatitude)));

        return new MapBounds(
            north: $centerLatitude + $latitudeDelta,
            south: $centerLatitude - $latitudeDelta,
            east: $centerLongitude + $longitudeDelta,
            west: $centerLongitude - $longitudeDelta,
        );
    }

    public function save(Event $event): Event
    {
        $model = $event->id !== null ? EventModel::findOrFail($event->id) : new EventModel();

        $model->fill([
            'created_by_type' => $event->createdByType->value,
            'created_by_id' => $event->createdById,
            'title' => $event->title,
            'description' => $event->description,
            'cover_image_url' => $event->coverImageUrl,
            'starts_at' => $event->startsAt,
            'city' => $event->city->value,
            'genre_id' => $event->genreId,
            'address' => $event->address,
            'latitude' => $event->latitude,
            'longitude' => $event->longitude,
            'is_free' => $event->isFree,
            'ticket_url' => $event->ticketUrl,
            'capacity' => $event->capacity,
            'age_rating' => $event->ageRating,
            'notes' => $event->notes,
            'status' => $event->status->value,
            'rejection_feedback' => $event->rejectionFeedback,
        ]);

        $model->save();
        $model->load('genre');

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        EventModel::destroy($id);
    }

    private function toDomain(EventModel $model): Event
    {
        return new Event(
            id: $model->id,
            createdByType: $model->created_by_type,
            createdById: $model->created_by_id,
            title: $model->title,
            description: $model->description,
            coverImageUrl: $model->cover_image_url,
            startsAt: $model->starts_at->toDateTimeImmutable(),
            city: $model->city,
            genreId: $model->genre_id,
            isFree: $model->is_free,
            status: $model->status,
            genreName: $model->genre->name,
            address: $model->address,
            ticketUrl: $model->ticket_url,
            capacity: $model->capacity,
            ageRating: $model->age_rating,
            notes: $model->notes,
            rejectionFeedback: $model->rejection_feedback,
            latitude: $model->latitude,
            longitude: $model->longitude,
        );
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function decodeCursor(string $cursor): array
    {
        /** @var array{starts_at: string, id: int} $decoded */
        $decoded = json_decode(base64_decode($cursor), true, flags: JSON_THROW_ON_ERROR);

        return [$decoded['starts_at'], (int) $decoded['id']];
    }

    private function encodeCursor(string $startsAt, int $id): string
    {
        return base64_encode(json_encode(['starts_at' => $startsAt, 'id' => $id], JSON_THROW_ON_ERROR));
    }
}
