<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventDetailResource;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'q' => ['sometimes', 'nullable', 'string'],
            'date_bucket' => ['sometimes', Rule::in(['hoje', 'amanha', 'fim_de_semana', 'proxima_semana'])],
            'city' => ['sometimes', Rule::in(['Vitória', 'Vila Velha', 'Serra', 'Cariacica'])],
            'genres' => ['sometimes', 'array'],
            'genres.*' => ['string'],
            'artist_id' => ['sometimes', 'string'],
        ]);

        $limit = $request->integer('limit', 20);
        $q = trim((string) ($validated['q'] ?? ''));

        $query = Event::query()
            ->with(['venue', 'artists', 'promoters'])
            ->publishedUpcoming();

        if ($q !== '') {
            $query->search($q)->exactMatchFirst($q);
        }
        if (! empty($validated['date_bucket'])) {
            $query->dateBucket($validated['date_bucket']);
        }
        if (! empty($validated['city'])) {
            $query->where('city', $validated['city']);
        }
        if (! empty($validated['genres'])) {
            $query->genres($validated['genres']);
        }
        if (! empty($validated['artist_id'])) {
            $query->whereHas('artists', fn (Builder $a) => $a->where('artists.id', $validated['artist_id']));
        }

        $query->orderBy('start_date_time')->orderBy('title')->orderBy('id');

        // Laravel's cursor paginator builds its keyset WHERE tuple from raw column names, but
        // exactMatchFirst()'s `is_exact_match` is a SELECT-list expression alias, not a real
        // table column — invalid in a Postgres WHERE clause, so cursorPaginate() 500s past page
        // one whenever q is active. Search result sets are small relative to the full feed, so
        // offset pagination's degradation risk doesn't apply here; the plain (q-less) feed keeps
        // the proven cursor paginator.
        return $q !== ''
            ? $this->offsetPaginatedResponse($query, $request, $limit)
            : $this->cursorPaginatedResponse($query, $request, $limit);
    }

    /** @param  Builder<Event>  $query */
    private function cursorPaginatedResponse(Builder $query, Request $request, int $limit): JsonResponse
    {
        $events = $query->cursorPaginate($limit);

        // Built explicitly (rather than returning EventResource::collection($events) directly)
        // to match the documented { data, next_cursor } contract — Laravel's default paginated
        // resource response nests next_cursor under meta instead.
        return response()->json([
            'data' => collect($events->items())
                ->map(fn (Event $event) => (new EventResource($event))->resolve($request))
                ->all(),
            'next_cursor' => $events->nextCursor()?->encode(),
        ]);
    }

    /** @param  Builder<Event>  $query */
    private function offsetPaginatedResponse(Builder $query, Request $request, int $limit): JsonResponse
    {
        $offset = 0;
        $cursorParam = $request->query('cursor');

        if (is_string($cursorParam) && $cursorParam !== '') {
            $decoded = json_decode(base64_decode($cursorParam, true) ?: '', true);
            if (is_array($decoded) && isset($decoded['offset'])) {
                $offset = max(0, (int) $decoded['offset']);
            }
        }

        $events = $query->offset($offset)->limit($limit + 1)->get();
        $hasMore = $events->count() > $limit;
        $items = $events->take($limit);

        return response()->json([
            'data' => $items->map(fn (Event $event) => (new EventResource($event))->resolve($request))->all(),
            'next_cursor' => $hasMore ? base64_encode(json_encode(['offset' => $offset + $limit])) : null,
        ]);
    }

    public function show(string $id): EventDetailResource|JsonResponse
    {
        $event = Event::query()
            ->with(['venue', 'promoters'])
            ->whereKey($id)
            // Published/Cancelled/persisted-Finished are all publicly resolvable (SHARE-003/005:
            // an old shared link must still resolve). Draft/PendingApproval/Approved/ChangesRequested
            // are internal-only and 404 here — they were never publicly published.
            ->whereIn('status', [EventStatus::Published, EventStatus::Cancelled, EventStatus::Finished])
            ->first();

        if ($event === null) {
            return response()->json(['message' => 'Evento não encontrado.'], 404);
        }

        return new EventDetailResource($event);
    }
}
