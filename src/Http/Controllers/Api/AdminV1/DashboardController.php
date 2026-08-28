<?php

namespace QOR\App\Http\Controllers\Api\AdminV1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Http\Controllers\Controller;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\PromoterModel;
use QOR\App\Infrastructure\Persistence\Eloquent\VenueModel;

class DashboardController extends Controller
{
    public function __construct(
        private readonly EventRepository $events,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var AdminUserModel $admin */
        $admin = $request->user();

        [$createdByType, $createdById] = $this->resolveOrganizerIdentity($admin);

        $events = $this->events->findByCreator($createdByType, $createdById);

        return response()->json([
            'data' => array_map(fn (Event $event) => $this->eventToArray($event), $events),
        ]);
    }

    /**
     * @return array{0: EventCreatedByType, 1: int}
     */
    private function resolveOrganizerIdentity(AdminUserModel $admin): array
    {
        $venue = VenueModel::where('venue_admin_user_id', $admin->id)->first();
        if ($venue !== null) {
            return [EventCreatedByType::VenueAdmin, (int) $venue->id];
        }

        $promoter = PromoterModel::where('user_id', $admin->id)->first();
        if ($promoter !== null) {
            return [EventCreatedByType::Promoter, (int) $promoter->id];
        }

        abort(403, 'Conta não é uma Venue ou Promoter registrada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function eventToArray(Event $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'starts_at' => $event->startsAt->format(DATE_ATOM),
            'status' => $event->status->value,
            // View/favorite/click/interested counts depend on the Favorites & Social milestone (not yet built) — explicitly null, not 0, so this isn't mistaken for real zero-activity data.
            'view_count' => null,
            'favorite_count' => null,
            'ticket_click_count' => null,
            'interested_count' => null,
        ];
    }
}
