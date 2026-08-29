<?php

namespace QOR\App\Infrastructure\Persistence;

use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Social\Enum\FavoriteState;
use QOR\App\Domain\Social\FavoritePage;
use QOR\App\Domain\Social\FavoriteRepository;
use QOR\App\Infrastructure\Persistence\Eloquent\FavoriteModel;

class EloquentFavoriteRepository implements FavoriteRepository
{
    public function __construct(
        private readonly EventRepository $eventRepository = new EloquentEventRepository(),
    ) {
    }

    public function toggle(int $userId, int $eventId): FavoriteState
    {
        $existing = FavoriteModel::where('user_id', $userId)
            ->where('event_id', $eventId)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return FavoriteState::Unfavorited;
        }

        FavoriteModel::create([
            'user_id' => $userId,
            'event_id' => $eventId,
            'created_at' => now(),
        ]);

        return FavoriteState::Favorited;
    }

    public function listForUser(int $userId, ?string $cursor): FavoritePage
    {
        /** @var int $pageSize */
        $pageSize = config('qor.pagination.public_page_size');

        $query = FavoriteModel::query()
            ->where('user_id', $userId)
            ->whereHas('event', fn ($q) => $q->where('status', EventStatus::Published->value))
            ->orderBy('created_at')
            ->orderBy('id');

        if ($cursor !== null) {
            [$cursorCreatedAt, $cursorId] = $this->decodeCursor($cursor);

            $query->where(function ($q) use ($cursorCreatedAt, $cursorId) {
                $q->where('created_at', '>', $cursorCreatedAt)
                    ->orWhere(function ($q2) use ($cursorCreatedAt, $cursorId) {
                        $q2->where('created_at', '=', $cursorCreatedAt)
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
            $nextCursor = $this->encodeCursor($last->created_at->toIso8601String(), $last->id);
        }

        $items = array_map(
            fn (FavoriteModel $favorite) => $this->eventRepository->findById($favorite->event_id),
            $models->all(),
        );

        return new FavoritePage(
            items: array_values(array_filter($items, fn ($event) => $event !== null)),
            nextCursor: $nextCursor,
        );
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function decodeCursor(string $cursor): array
    {
        /** @var array{created_at: string, id: int} $decoded */
        $decoded = json_decode(base64_decode($cursor), true, flags: JSON_THROW_ON_ERROR);

        return [$decoded['created_at'], (int) $decoded['id']];
    }

    private function encodeCursor(string $createdAt, int $id): string
    {
        return base64_encode(json_encode(['created_at' => $createdAt, 'id' => $id], JSON_THROW_ON_ERROR));
    }
}
