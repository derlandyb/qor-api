<?php

namespace QOR\App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use QOR\App\Domain\Social\FriendPage;
use QOR\App\Domain\Social\Friendship;
use QOR\App\Domain\Social\UseCase\ListFriends;
use QOR\App\Domain\Social\UseCase\RemoveFriend;
use QOR\App\Domain\Social\UseCase\RespondToFriendRequest;
use QOR\App\Domain\Social\UseCase\SendFriendRequest;
use QOR\App\Domain\User\User;
use QOR\App\Domain\User\UserRepository;
use QOR\App\Http\Controllers\Controller;
use QOR\App\Http\Requests\Api\V1\SendFriendRequestRequest;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;

class FriendshipController extends Controller
{
    public function __construct(
        private readonly SendFriendRequest $sendFriendRequest,
        private readonly RespondToFriendRequest $respondToFriendRequest,
        private readonly RemoveFriend $removeFriend,
        private readonly ListFriends $listFriends,
        private readonly UserRepository $users,
    ) {
    }

    public function store(SendFriendRequestRequest $request): JsonResponse
    {
        /** @var UserModel $model */
        $model = $request->user();

        $friendship = $this->sendFriendRequest->execute((int) $model->id, $request->recipientUserId());

        return response()->json(['data' => $this->friendshipToArray($friendship, (int) $model->id)], 201);
    }

    public function incoming(Request $request): JsonResponse
    {
        /** @var UserModel $model */
        $model = $request->user();

        $requests = $this->respondToFriendRequest->listIncoming((int) $model->id);

        return response()->json([
            'data' => array_map(
                fn (Friendship $friendship) => $this->friendshipToArray($friendship, (int) $model->id),
                $requests,
            ),
        ]);
    }

    public function accept(Request $request, int $id): JsonResponse
    {
        /** @var UserModel $model */
        $model = $request->user();

        $friendship = $this->respondToFriendRequest->accept($id, (int) $model->id);

        return response()->json(['data' => $this->friendshipToArray($friendship, (int) $model->id)]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        /** @var UserModel $model */
        $model = $request->user();

        $this->respondToFriendRequest->reject($id, (int) $model->id);

        return response()->json(['message' => 'Solicitação de amizade recusada.']);
    }

    public function destroy(Request $request, int $userId): JsonResponse
    {
        /** @var UserModel $model */
        $model = $request->user();

        $this->removeFriend->execute((int) $model->id, $userId);

        return response()->json(['message' => 'Amizade removida.']);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var UserModel $model */
        $model = $request->user();
        /** @var string|null $cursor */
        $cursor = $request->query('cursor');

        $page = $this->listFriends->execute((int) $model->id, $cursor);

        return response()->json($this->pageToArray($page, (int) $model->id));
    }

    /**
     * @return array{data: list<array<string, mixed>>, next_cursor: ?string}
     */
    private function pageToArray(FriendPage $page, int $currentUserId): array
    {
        return [
            'data' => array_map(
                fn (Friendship $friendship) => $this->friendshipToArray($friendship, $currentUserId),
                $page->items,
            ),
            'next_cursor' => $page->nextCursor,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function friendshipToArray(Friendship $friendship, int $currentUserId): array
    {
        $otherUserId = $friendship->requesterId === $currentUserId
            ? $friendship->recipientId
            : $friendship->requesterId;

        $otherUser = $this->users->findById($otherUserId);

        return [
            'id' => $friendship->id,
            'status' => $friendship->status->value,
            'requester_user_id' => $friendship->requesterId,
            'recipient_user_id' => $friendship->recipientId,
            'friend' => $otherUser !== null ? $this->userToArray($otherUser) : null,
        ];
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
