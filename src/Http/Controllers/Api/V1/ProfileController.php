<?php

namespace QOR\App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use QOR\App\Domain\Shared\UploadableFile;
use QOR\App\Domain\User\User;
use QOR\App\Domain\User\UseCase\ExerciseDataRight;
use QOR\App\Domain\User\UseCase\UpdateProfile;
use QOR\App\Http\Controllers\Controller;
use QOR\App\Http\Requests\Api\V1\RevokeConsentRequest;
use QOR\App\Http\Requests\Api\V1\UpdateProfileRequest;
use QOR\App\Http\Requests\Api\V1\UploadProfilePictureRequest;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;

class ProfileController extends Controller
{
    public function __construct(
        private readonly UpdateProfile $updateProfile,
        private readonly ExerciseDataRight $exerciseDataRight,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        /** @var UserModel $model */
        $model = $request->user();

        return response()->json(['data' => [
            'id' => $model->id,
            'name' => $model->name,
            'email' => $model->email,
            'phone' => $model->phone,
            'birthdate' => $model->birthdate->format('Y-m-d'),
            'profile_picture_url' => $model->profile_picture_url,
            'email_verified' => $model->hasVerifiedEmail(),
        ]]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var UserModel $model */
        $model = $request->user();

        /** @var string|null $name */
        $name = $request->validated('name');
        /** @var string|null $phone */
        $phone = $request->validated('phone');
        /** @var string|null $email */
        $email = $request->validated('email');

        $user = $this->updateProfile->execute(
            userId: (int) $model->id,
            name: $name,
            phone: $phone,
            newEmail: $email,
        );

        return response()->json(['data' => $this->userToArray($user)]);
    }

    public function updatePicture(UploadProfilePictureRequest $request): JsonResponse
    {
        /** @var UserModel $model */
        $model = $request->user();
        /** @var UploadedFile $file */
        $file = $request->file('picture');

        $user = $this->updateProfile->execute(
            userId: (int) $model->id,
            picture: $this->toUploadableFile($file),
        );

        return response()->json(['data' => $this->userToArray($user)]);
    }

    public function dataRightsAccess(Request $request): JsonResponse
    {
        /** @var UserModel $model */
        $model = $request->user();

        return response()->json(['data' => $this->exerciseDataRight->access((int) $model->id)]);
    }

    public function dataRightsExport(Request $request): JsonResponse
    {
        /** @var UserModel $model */
        $model = $request->user();
        $export = $this->exerciseDataRight->export((int) $model->id);

        return response()->json(['data' => json_decode($export, true, flags: JSON_THROW_ON_ERROR)]);
    }

    public function dataRightsDelete(Request $request): JsonResponse
    {
        /** @var UserModel $model */
        $model = $request->user();

        $this->exerciseDataRight->delete((int) $model->id);
        $model->currentAccessToken()->delete();

        return response()->json(['message' => 'Conta excluída com sucesso.']);
    }

    public function dataRightsRevoke(RevokeConsentRequest $request): JsonResponse
    {
        /** @var UserModel $model */
        $model = $request->user();

        $this->exerciseDataRight->revokeConsent((int) $model->id, $request->consentType());

        return response()->json(['message' => 'Consentimento revogado com sucesso.']);
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
    private function userToArray(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_picture_url' => $user->profilePictureUrl,
            'email_verified' => $user->isVerified(),
            'pending_email' => $user->pendingEmail,
        ];
    }
}
