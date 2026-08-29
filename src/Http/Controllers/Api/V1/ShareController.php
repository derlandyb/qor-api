<?php

namespace QOR\App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Social\UseCase\GetFriendsInterested;
use QOR\App\Domain\Social\UseCase\ShareEvent;
use QOR\App\Domain\User\User;
use QOR\App\Http\Controllers\Controller;
use QOR\App\Http\Requests\Api\V1\ShareEventRequest;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;

class ShareController extends Controller
{
    public function __construct(
        private readonly GetFriendsInterested $getFriendsInterested,
        private readonly ShareEvent $shareEvent,
        private readonly EventRepository $events,
    ) {
    }

    public function friendsInterested(Request $request, int $id): JsonResponse
    {
        if ($this->events->findById($id) === null) {
            return response()->json(['message' => 'Evento não encontrado.'], 404);
        }

        /** @var UserModel $model */
        $model = $request->user();

        $interested = $this->getFriendsInterested->execute((int) $model->id, $id);

        return response()->json([
            'data' => array_map(fn (User $user) => $this->userToArray($user), $interested),
        ]);
    }

    public function share(ShareEventRequest $request, int $id): JsonResponse
    {
        if ($this->events->findById($id) === null) {
            return response()->json(['message' => 'Evento não encontrado.'], 404);
        }

        /** @var UserModel $model */
        $model = $request->user();

        $this->shareEvent->shareToFriend((int) $model->id, $request->friendUserId(), $id);

        return response()->json(['message' => 'Evento compartilhado com sucesso.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function userToArray(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'profile_picture_url' => $user->profilePictureUrl,
        ];
    }
}
