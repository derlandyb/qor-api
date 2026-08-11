<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventDetailResource;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $limit = $request->integer('limit', 20);

        $events = Event::query()
            ->with(['venue', 'artists', 'promoters'])
            ->publishedUpcoming()
            ->orderBy('start_date_time')
            ->orderBy('title')
            ->orderBy('id')
            ->cursorPaginate($limit);

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
