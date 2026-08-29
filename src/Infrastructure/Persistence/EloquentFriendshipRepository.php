<?php

namespace QOR\App\Infrastructure\Persistence;

use QOR\App\Domain\Social\Enum\FriendshipStatus;
use QOR\App\Domain\Social\FriendPage;
use QOR\App\Domain\Social\Friendship;
use QOR\App\Domain\Social\FriendshipRepository;
use QOR\App\Infrastructure\Persistence\Eloquent\FriendshipModel;

class EloquentFriendshipRepository implements FriendshipRepository
{
    public function create(int $requesterId, int $recipientId): Friendship
    {
        $model = FriendshipModel::create([
            'requester_id' => $requesterId,
            'recipient_id' => $recipientId,
            'status' => FriendshipStatus::Pending->value,
        ]);

        return $this->toDomain($model);
    }

    public function accept(int $friendshipId): Friendship
    {
        $model = FriendshipModel::findOrFail($friendshipId);
        $model->status = FriendshipStatus::Accepted->value;
        $model->save();

        return $this->toDomain($model);
    }

    public function reject(int $friendshipId): void
    {
        FriendshipModel::destroy($friendshipId);
    }

    public function remove(int $userId, int $friendUserId): void
    {
        FriendshipModel::where(function ($q) use ($userId, $friendUserId) {
            $q->where('requester_id', $userId)->where('recipient_id', $friendUserId);
        })->orWhere(function ($q) use ($userId, $friendUserId) {
            $q->where('requester_id', $friendUserId)->where('recipient_id', $userId);
        })->delete();
    }

    public function listAccepted(int $userId, ?string $cursor): FriendPage
    {
        /** @var int $pageSize */
        $pageSize = config('qor.pagination.public_page_size');

        $query = FriendshipModel::query()
            ->where('status', FriendshipStatus::Accepted->value)
            ->where(function ($q) use ($userId) {
                $q->where('requester_id', $userId)->orWhere('recipient_id', $userId);
            })
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

        return new FriendPage(
            items: array_values($models->map(fn (FriendshipModel $model) => $this->toDomain($model))->all()),
            nextCursor: $nextCursor,
        );
    }

    public function listIncomingPending(int $userId): array
    {
        $models = FriendshipModel::where('recipient_id', $userId)
            ->where('status', FriendshipStatus::Pending->value)
            ->orderByDesc('created_at')
            ->get();

        return array_values($models->map(fn (FriendshipModel $model) => $this->toDomain($model))->all());
    }

    public function findBetween(int $userIdA, int $userIdB): ?Friendship
    {
        $model = FriendshipModel::where(function ($q) use ($userIdA, $userIdB) {
            $q->where('requester_id', $userIdA)->where('recipient_id', $userIdB);
        })->orWhere(function ($q) use ($userIdA, $userIdB) {
            $q->where('requester_id', $userIdB)->where('recipient_id', $userIdA);
        })->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    private function toDomain(FriendshipModel $model): Friendship
    {
        return new Friendship(
            id: $model->id,
            requesterId: $model->requester_id,
            recipientId: $model->recipient_id,
            status: $model->status,
            createdAt: $model->created_at?->toDateTimeImmutable(),
            updatedAt: $model->updated_at?->toDateTimeImmutable(),
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
