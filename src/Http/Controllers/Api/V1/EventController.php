<?php

namespace QOR\App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\EventDetail;
use QOR\App\Domain\Event\EventPage;
use QOR\App\Domain\Event\UseCase\GetEventDetails;
use QOR\App\Domain\Event\UseCase\ListUpcomingEvents;
use QOR\App\Domain\Promoter\Promoter;
use QOR\App\Http\Controllers\Controller;
use QOR\App\Http\Requests\Api\V1\ListEventsRequest;

class EventController extends Controller
{
    public function __construct(
        private readonly ListUpcomingEvents $listUpcomingEvents,
        private readonly GetEventDetails $getEventDetails,
    ) {
    }

    public function index(ListEventsRequest $request): JsonResponse
    {
        $page = $this->listUpcomingEvents->execute(
            city: $request->city(),
            genreId: $request->genreId(),
            cursor: $request->cursor(),
        );

        return response()->json($this->pageToArray($page));
    }

    public function show(int $id): JsonResponse
    {
        $detail = $this->getEventDetails->execute($id);

        if ($detail === null) {
            return response()->json(['message' => 'Evento não encontrado.'], 404);
        }

        return response()->json(['data' => $this->detailToArray($detail)]);
    }

    /**
     * @return array{data: list<array<string, mixed>>, next_cursor: ?string}
     */
    private function pageToArray(EventPage $page): array
    {
        return [
            'data' => array_map(fn (Event $event) => $this->eventToArray($event), $page->items),
            'next_cursor' => $page->nextCursor,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailToArray(EventDetail $detail): array
    {
        return [
            ...$this->eventToArray($detail->event),
            'tagged_promoters' => array_map(
                fn (Promoter $promoter) => $this->promoterToArray($promoter),
                $detail->taggedPromoters,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventToArray(Event $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'cover_image_url' => $event->coverImageUrl,
            'starts_at' => $event->startsAt->format(DATE_ATOM),
            'city' => $event->city->value,
            'genre_id' => $event->genreId,
            'address' => $event->address,
            'is_free' => $event->isFree,
            'ticket_url' => $event->ticketUrl,
            'capacity' => $event->capacity,
            'age_rating' => $event->ageRating,
            'notes' => $event->notes,
            'status' => $event->status->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function promoterToArray(Promoter $promoter): array
    {
        return [
            'id' => $promoter->id,
            'name' => $promoter->name,
            'contact_phone' => $promoter->contactPhone,
            'contact_email' => $promoter->contactEmail,
            'instagram' => $promoter->instagram,
            'tiktok' => $promoter->tiktok,
        ];
    }
}
