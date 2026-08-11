<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min((int) $request->integer('limit', 20), 50);

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
}
