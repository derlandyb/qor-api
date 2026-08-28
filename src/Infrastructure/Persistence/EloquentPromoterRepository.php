<?php

namespace QOR\App\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use QOR\App\Domain\Promoter\Promoter;
use QOR\App\Domain\Promoter\PromoterRepository;
use QOR\App\Infrastructure\Persistence\Eloquent\PromoterModel;

class EloquentPromoterRepository implements PromoterRepository
{
    public function findById(int $id): ?Promoter
    {
        $model = PromoterModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findTaggedForEvent(int $eventId): array
    {
        /** @var \Illuminate\Support\Collection<int, int> $promoterIds */
        $promoterIds = DB::table('event_promoter')
            ->where('event_id', $eventId)
            ->orderBy('tagged_at')
            ->pluck('promoter_id');

        $models = PromoterModel::whereIn('id', $promoterIds)->get()->keyBy('id');

        $promoters = [];
        foreach ($promoterIds as $promoterId) {
            /** @var PromoterModel|null $model */
            $model = $models->get($promoterId);

            if ($model !== null) {
                $promoters[] = $this->toDomain($model);
            }
        }

        return $promoters;
    }

    public function findTaggedForEvents(array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        $links = DB::table('event_promoter')
            ->whereIn('event_id', $eventIds)
            ->orderBy('tagged_at')
            ->get(['event_id', 'promoter_id']);

        $promoterIds = $links->pluck('promoter_id')->unique()->values();
        $models = PromoterModel::whereIn('id', $promoterIds)->get()->keyBy('id');

        $result = [];
        foreach ($links as $link) {
            $model = $models->get($link->promoter_id);
            if ($model !== null) {
                $result[$link->event_id][] = $this->toDomain($model);
            }
        }

        return $result;
    }

    public function tagEvent(int $eventId, array $promoterIds): void
    {
        DB::transaction(function () use ($eventId, $promoterIds): void {
            DB::table('event_promoter')->where('event_id', $eventId)->delete();

            if ($promoterIds === []) {
                return;
            }

            $now = now();
            $rows = array_map(
                static fn (int $promoterId): array => [
                    'event_id' => $eventId,
                    'promoter_id' => $promoterId,
                    'tagged_at' => $now,
                ],
                $promoterIds,
            );

            DB::table('event_promoter')->insert($rows);
        });
    }

    public function save(Promoter $promoter): Promoter
    {
        $model = $promoter->id !== null ? PromoterModel::findOrFail($promoter->id) : new PromoterModel();

        $model->fill([
            'user_id' => $promoter->userId,
            'name' => $promoter->name,
            'contact_phone' => $promoter->contactPhone,
            'contact_email' => $promoter->contactEmail,
            'instagram' => $promoter->instagram,
            'tiktok' => $promoter->tiktok,
            'approval_status' => $promoter->approvalStatus->value,
        ]);

        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        PromoterModel::destroy($id);
    }

    private function toDomain(PromoterModel $model): Promoter
    {
        return new Promoter(
            id: $model->id,
            userId: $model->user_id,
            name: $model->name,
            contactPhone: $model->contact_phone,
            contactEmail: $model->contact_email,
            approvalStatus: $model->approval_status,
            instagram: $model->instagram,
            tiktok: $model->tiktok,
        );
    }
}
