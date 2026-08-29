<?php

namespace QOR\App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Social\Enum\FavoriteState;
use QOR\App\Domain\Social\FavoritePage;
use QOR\App\Domain\Social\UseCase\ToggleFavorite;
use QOR\App\Http\Controllers\Controller;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;

class FavoriteController extends Controller
{
    public function __construct(
        private readonly ToggleFavorite $toggleFavorite,
        private readonly EventRepository $events,
    ) {
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        if ($this->events->findById($id) === null) {
            return response()->json(['message' => 'Evento não encontrado.'], 404);
        }

        /** @var UserModel $model */
        $model = $request->user();

        $state = $this->toggleFavorite->execute((int) $model->id, $id);

        return response()->json(['data' => [
            'event_id' => $id,
            'favorited' => $state === FavoriteState::Favorited,
        ]]);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var UserModel $model */
        $model = $request->user();
        /** @var string|null $cursor */
        $cursor = $request->query('cursor');

        $page = $this->toggleFavorite->listForUser((int) $model->id, $cursor);

        return response()->json($this->pageToArray($page));
    }

    /**
     * @return array{data: list<array<string, mixed>>, next_cursor: ?string}
     */
    private function pageToArray(FavoritePage $page): array
    {
        return [
            'data' => array_map(fn (Event $event) => $this->eventToArray($event), $page->items),
            'next_cursor' => $page->nextCursor,
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
}
