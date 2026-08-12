<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\EventBannerStatusResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FavoriteController extends Controller
{
    public function store(Request $request, Event $event): JsonResponse
    {
        // syncWithoutDetaching is naturally idempotent: a rapid double-tap that both
        // resolve to "add" produces exactly one row, no duplicate-key exception.
        $request->user()->favoriteEvents()->syncWithoutDetaching([$event->id]);

        return response()->json(['favorited' => true]);
    }

    public function destroy(Request $request, Event $event): JsonResponse
    {
        $request->user()->favoriteEvents()->detach($event->id); // idempotent: no-op if already absent

        return response()->json(['favorited' => false]);
    }

    public function index(Request $request): JsonResponse
    {
        $favorited = $request->user()->favoriteEvents()->with('venue')->get();

        [$passados, $upcoming] = $favorited->partition(
            fn (Event $event) => EventBannerStatusResolver::isFinished($event)
        );

        return response()->json([
            'upcoming' => EventResource::collection($upcoming->sortBy('start_date_time')->values()),
            'passados' => EventResource::collection($passados->sortByDesc('start_date_time')->values()),
        ]);
    }
}
