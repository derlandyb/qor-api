<?php

namespace QOR\App\Infrastructure\Persistence;

use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\EventPage;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Infrastructure\Persistence\Eloquent\EventModel;

class EloquentEventRepository implements EventRepository
{
    public function findUpcoming(?City $city, ?int $genreId, ?string $cursor): EventPage
    {
        /** @var int $pageSize */
        $pageSize = config('qor.pagination.public_page_size');

        $query = EventModel::query()
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
        $model = EventModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $models = EventModel::whereIn('id', $ids)->get();

        return array_values($models->map(fn (EventModel $model) => $this->toDomain($model))->all());
    }

    public function findByCreator(EventCreatedByType $createdByType, int $createdById): array
    {
        $models = EventModel::where('created_by_type', $createdByType->value)
            ->where('created_by_id', $createdById)
            ->orderByDesc('starts_at')
            ->get();

        return array_values($models->map(fn (EventModel $model) => $this->toDomain($model))->all());
    }

    public function findPublishedPastEnd(): array
    {
        $models = EventModel::where('status', EventStatus::Published->value)
            ->where('starts_at', '<', now())
            ->get();

        return array_values($models->map(fn (EventModel $model) => $this->toDomain($model))->all());
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
            'is_free' => $event->isFree,
            'ticket_url' => $event->ticketUrl,
            'capacity' => $event->capacity,
            'age_rating' => $event->ageRating,
            'notes' => $event->notes,
            'status' => $event->status->value,
            'rejection_feedback' => $event->rejectionFeedback,
        ]);

        $model->save();

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
            address: $model->address,
            ticketUrl: $model->ticket_url,
            capacity: $model->capacity,
            ageRating: $model->age_rating,
            notes: $model->notes,
            rejectionFeedback: $model->rejection_feedback,
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
