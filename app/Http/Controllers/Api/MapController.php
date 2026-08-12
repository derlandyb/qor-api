<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

final class MapController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'date_bucket' => ['sometimes', Rule::in(['hoje', 'amanha', 'fim_de_semana', 'proxima_semana'])],
            'city' => ['sometimes', Rule::in(['Vitória', 'Vila Velha', 'Serra', 'Cariacica'])],
            'genres' => ['sometimes', 'array'],
            'genres.*' => ['string'],
            'artist_id' => ['sometimes', 'string'],
        ]);

        $this->attachFavoritedEventIds($request);

        $query = Event::query()
            ->with(['venue', 'artists', 'promoters'])
            ->publishedUpcoming()
            ->hasVenueCoordinates();

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

        // No cursorPaginate(): a partial page would produce incorrect cluster counts client-side.
        // Hard safety cap instead, ordered soonest-first.
        $events = $query->orderBy('start_date_time')->limit(config('map.max_markers', 500))->get();

        return EventResource::collection($events);
    }
}
