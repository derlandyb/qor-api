<?php

namespace QOR\App\Http\Controllers\Api\AdminV1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use QOR\App\Domain\Approval\ApprovalDecision;
use QOR\App\Domain\Approval\ApprovalDecisionRepository;
use QOR\App\Domain\Approval\Enum\ApprovalOutcome;
use QOR\App\Domain\Approval\UseCase\DecideEventApproval;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Http\Controllers\Controller;
use QOR\App\Http\Requests\Api\AdminV1\DecideEventApprovalRequest;

class EventApprovalController extends Controller
{
    public function __construct(
        private readonly ApprovalDecisionRepository $approvalDecisionRepository,
        private readonly EventRepository $eventRepository,
        private readonly DecideEventApproval $decideEventApproval,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $pendingIds = $this->approvalDecisionRepository->findPendingEvents();

        /** @var int $pageSize */
        $pageSize = config('qor.pagination.admin_page_size');

        $page = max(1, (int) $request->query('page', 1));

        $total = count($pendingIds);
        $pageIds = array_slice($pendingIds, ($page - 1) * $pageSize, $pageSize);

        $events = array_values(array_filter(array_map(
            fn (int $id) => $this->eventRepository->findById($id),
            $pageIds,
        )));

        return response()->json([
            'data' => array_map(fn (Event $event) => $this->eventToArray($event), $events),
            'current_page' => $page,
            'per_page' => $pageSize,
            'total' => $total,
        ]);
    }

    public function decide(DecideEventApprovalRequest $request, int $id): JsonResponse
    {
        /** @var int $decidedBy */
        $decidedBy = Auth::guard('admin')->id();

        /** @var string $outcome */
        $outcome = $request->validated('outcome');

        /** @var string|null $feedback */
        $feedback = $request->validated('feedback');

        $decision = $this->decideEventApproval->execute(
            eventId: $id,
            outcome: ApprovalOutcome::from($outcome),
            feedback: $feedback,
            decidedBy: $decidedBy,
        );

        return response()->json(['data' => $this->decisionToArray($decision)]);
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
            'created_by_type' => $event->createdByType->value,
            'created_by_id' => $event->createdById,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decisionToArray(ApprovalDecision $decision): array
    {
        return [
            'id' => $decision->id,
            'decidable_type' => $decision->decidableType->value,
            'decidable_id' => $decision->decidableId,
            'outcome' => $decision->outcome->value,
            'reason' => $decision->reason,
            'decided_by' => $decision->decidedBy,
            'decided_at' => $decision->decidedAt->format(DATE_ATOM),
        ];
    }
}
