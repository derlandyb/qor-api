<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ArtistOptionResource;
use App\Models\Artist;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class FilterOptionsController extends Controller
{
    public function genres(): JsonResponse
    {
        $genres = DB::table('events')
            ->selectRaw('DISTINCT jsonb_array_elements_text(genres) AS genre')
            ->where('status', EventStatus::Published->value)
            ->where('start_date_time', '>', now())
            ->orderBy('genre')
            ->pluck('genre');

        return response()->json(['data' => $genres]);
    }

    public function artists(): JsonResponse
    {
        $artists = Artist::query()
            ->whereHas('events', function (Builder $q) {
                /** @var Builder<Event> $q */
                return $q->strictlyPublishedUpcoming();
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'data' => ArtistOptionResource::collection($artists)->resolve(),
        ]);
    }
}
