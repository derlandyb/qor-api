<?php

namespace QOR\App\Http\Controllers\Api\AdminV1;

use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Event;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Event\UseCase\CancelEvent;
use QOR\App\Domain\Event\UseCase\CreateEvent;
use QOR\App\Domain\Event\UseCase\DuplicateEvent;
use QOR\App\Domain\Event\UseCase\EditEvent;
use QOR\App\Domain\Event\UseCase\SubmitEventForReview;
use QOR\App\Domain\Promoter\Promoter;
use QOR\App\Domain\Promoter\PromoterRepository;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\Shared\UploadableFile;
use QOR\App\Domain\Venue\Venue;
use QOR\App\Domain\Venue\VenueRepository;
use QOR\App\Http\Controllers\Controller;
use QOR\App\Http\Policies\EventPolicy;
use QOR\App\Http\Requests\Api\AdminV1\CreateEventRequest;
use QOR\App\Http\Requests\Api\AdminV1\EditEventRequest;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\EventModel;
use QOR\App\Infrastructure\Persistence\Eloquent\PromoterModel;
use QOR\App\Infrastructure\Persistence\Eloquent\VenueModel;

class EventController extends Controller
{
    public function __construct(
        private readonly CreateEvent $createEvent,
        private readonly SubmitEventForReview $submitEventForReview,
        private readonly EditEvent $editEvent,
        private readonly DuplicateEvent $duplicateEvent,
        private readonly CancelEvent $cancelEvent,
        private readonly EventRepository $events,
        private readonly VenueRepository $venues,
        private readonly PromoterRepository $promoters,
        private readonly EventPolicy $policy,
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

    public function store(CreateEventRequest $request): JsonResponse
    {
        /** @var AdminUserModel $admin */
        $admin = $request->user();

        [$createdByType, $organizer] = $this->resolveOrganizer($admin);

        /** @var string $title */
        $title = $request->validated('title');
        /** @var string $description */
        $description = $request->validated('description');
        /** @var string $startsAt */
        $startsAt = $request->validated('starts_at');
        /** @var string $city */
        $city = $request->validated('city');
        /** @var int|string $genreId */
        $genreId = $request->validated('genre_id');
        /** @var string|null $address */
        $address = $request->validated('address');
        /** @var string|null $ticketUrl */
        $ticketUrl = $request->validated('ticket_url');
        /** @var int|string|null $capacity */
        $capacity = $request->validated('capacity');
        /** @var string|null $ageRating */
        $ageRating = $request->validated('age_rating');
        /** @var string|null $notes */
        $notes = $request->validated('notes');

        $coverImage = null;
        if ($request->hasFile('cover_image')) {
            /** @var UploadedFile $file */
            $file = $request->file('cover_image');
            $coverImage = $this->toUploadableFile($file);
        }

        $event = $this->createEvent->execute(
            createdByType: $createdByType,
            organizer: $organizer,
            title: $title,
            description: $description,
            startsAt: new DateTimeImmutable($startsAt),
            city: City::from($city),
            genreId: (int) $genreId,
            isFree: $request->boolean('is_free'),
            address: $address,
            coverImage: $coverImage,
            ticketUrl: $ticketUrl,
            capacity: $capacity !== null ? (int) $capacity : null,
            ageRating: $ageRating,
            notes: $notes,
        );

        return response()->json(['data' => $this->eventToArray($event)], 201);
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        /** @var AdminUserModel $admin */
        $admin = $request->user();

        [$createdByType, $organizer] = $this->resolveOrganizer($admin);

        $eventModel = EventModel::find($id);
        if ($eventModel === null) {
            return response()->json(['message' => 'Evento não encontrado.'], 404);
        }

        if (! $this->policy->submit($admin, $eventModel, $this->organizerModel($admin, $createdByType))) {
            return response()->json(['message' => 'Você não tem permissão para esta ação.'], 403);
        }

        $event = $this->submitEventForReview->execute($id, $organizer);

        return response()->json(['data' => $this->eventToArray($event)]);
    }

    public function update(EditEventRequest $request, int $id): JsonResponse
    {
        /** @var AdminUserModel $admin */
        $admin = $request->user();

        [$createdByType, $organizer] = $this->resolveOrganizer($admin);

        $eventModel = EventModel::find($id);
        if ($eventModel === null) {
            return response()->json(['message' => 'Evento não encontrado.'], 404);
        }

        if (! $this->policy->update($admin, $eventModel, $this->organizerModel($admin, $createdByType))) {
            return response()->json(['message' => 'Você não tem permissão para esta ação.'], 403);
        }

        /** @var string|null $title */
        $title = $request->validated('title') ?? null;
        /** @var string|null $description */
        $description = $request->validated('description') ?? null;
        /** @var string|null $startsAt */
        $startsAt = $request->validated('starts_at') ?? null;
        /** @var string|null $city */
        $city = $request->validated('city') ?? null;
        /** @var int|string|null $genreId */
        $genreId = $request->validated('genre_id') ?? null;
        /** @var string|null $address */
        $address = $request->validated('address') ?? null;
        /** @var string|null $ticketUrl */
        $ticketUrl = $request->validated('ticket_url') ?? null;
        /** @var int|string|null $capacity */
        $capacity = $request->validated('capacity') ?? null;
        /** @var string|null $ageRating */
        $ageRating = $request->validated('age_rating') ?? null;
        /** @var string|null $notes */
        $notes = $request->validated('notes') ?? null;

        $coverImage = null;
        if ($request->hasFile('cover_image')) {
            /** @var UploadedFile $file */
            $file = $request->file('cover_image');
            $coverImage = $this->toUploadableFile($file);
        }

        $isFree = $request->has('is_free') ? $request->boolean('is_free') : null;

        $event = $this->editEvent->execute(
            eventId: $id,
            organizer: $organizer,
            title: $title,
            description: $description,
            startsAt: $startsAt !== null ? new DateTimeImmutable($startsAt) : null,
            city: $city !== null ? City::from($city) : null,
            genreId: $genreId !== null ? (int) $genreId : null,
            isFree: $isFree,
            address: $address,
            coverImage: $coverImage,
            ticketUrl: $ticketUrl,
            capacity: $capacity !== null ? (int) $capacity : null,
            ageRating: $ageRating,
            notes: $notes,
        );

        return response()->json(['data' => $this->eventToArray($event)]);
    }

    public function duplicate(Request $request, int $id): JsonResponse
    {
        /** @var AdminUserModel $admin */
        $admin = $request->user();

        [$createdByType, $organizer] = $this->resolveOrganizer($admin);

        $eventModel = EventModel::find($id);
        if ($eventModel === null) {
            return response()->json(['message' => 'Evento não encontrado.'], 404);
        }

        if (! $this->policy->update($admin, $eventModel, $this->organizerModel($admin, $createdByType))) {
            return response()->json(['message' => 'Você não tem permissão para esta ação.'], 403);
        }

        $validated = $request->validate(['starts_at' => ['required', 'date']]);
        /** @var string $startsAt */
        $startsAt = $validated['starts_at'];

        $event = $this->duplicateEvent->execute(
            eventId: $id,
            organizer: $organizer,
            startsAt: new DateTimeImmutable($startsAt),
        );

        return response()->json(['data' => $this->eventToArray($event)], 201);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        /** @var AdminUserModel $admin */
        $admin = $request->user();

        [$createdByType, $organizer] = $this->resolveOrganizer($admin);

        $eventModel = EventModel::find($id);
        if ($eventModel === null) {
            return response()->json(['message' => 'Evento não encontrado.'], 404);
        }

        if (! $this->policy->update($admin, $eventModel, $this->organizerModel($admin, $createdByType))) {
            return response()->json(['message' => 'Você não tem permissão para esta ação.'], 403);
        }

        $event = $this->cancelEvent->execute($id, $organizer);

        return response()->json(['data' => $this->eventToArray($event)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var AdminUserModel $admin */
        $admin = $request->user();

        [$createdByType] = $this->resolveOrganizer($admin);

        $eventModel = EventModel::find($id);
        if ($eventModel === null) {
            return response()->json(['message' => 'Evento não encontrado.'], 404);
        }

        if (! $this->policy->delete($admin, $eventModel, $this->organizerModel($admin, $createdByType))) {
            return response()->json(['message' => 'Você não tem permissão para esta ação.'], 403);
        }

        $this->events->delete($id);

        return response()->json(['message' => 'Evento excluído com sucesso.']);
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
     * @return array{0: EventCreatedByType, 1: Venue|Promoter}
     */
    private function resolveOrganizer(AdminUserModel $admin): array
    {
        [$createdByType, $createdById] = $this->resolveOrganizerIdentity($admin);

        if ($createdByType === EventCreatedByType::VenueAdmin) {
            $venue = $this->venues->findById($createdById);
            if ($venue === null) {
                abort(403, 'Conta não é uma Venue ou Promoter registrada.');
            }

            return [$createdByType, $venue];
        }

        $promoter = $this->promoters->findById($createdById);
        if ($promoter === null) {
            abort(403, 'Conta não é uma Venue ou Promoter registrada.');
        }

        return [$createdByType, $promoter];
    }

    private function organizerModel(AdminUserModel $admin, EventCreatedByType $createdByType): VenueModel|PromoterModel
    {
        if ($createdByType === EventCreatedByType::VenueAdmin) {
            /** @var VenueModel $venue */
            $venue = VenueModel::where('venue_admin_user_id', $admin->id)->firstOrFail();

            return $venue;
        }

        /** @var PromoterModel $promoter */
        $promoter = PromoterModel::where('user_id', $admin->id)->firstOrFail();

        return $promoter;
    }

    private function toUploadableFile(UploadedFile $file): UploadableFile
    {
        $dimensions = @getimagesize((string) $file->getRealPath());

        return new UploadableFile(
            path: (string) $file->getRealPath(),
            originalName: (string) ($file->getClientOriginalName() ?: 'upload'),
            mimeType: (string) ($file->getMimeType() ?: 'application/octet-stream'),
            sizeBytes: (int) $file->getSize(),
            widthPx: $dimensions !== false ? (int) $dimensions[0] : null,
            heightPx: $dimensions !== false ? (int) $dimensions[1] : null,
        );
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
            'rejection_feedback' => $event->rejectionFeedback,
            'created_by_type' => $event->createdByType->value,
            'created_by_id' => $event->createdById,
        ];
    }
}
