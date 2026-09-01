<?php

namespace QOR\App\Http\Controllers\Api\AdminV1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use QOR\App\Domain\Promoter\Promoter;
use QOR\App\Domain\Promoter\PromoterRepository;
use QOR\App\Domain\Promoter\UseCase\EditPromoterProfile;
use QOR\App\Domain\Promoter\UseCase\RegisterPromoter;
use QOR\App\Http\Controllers\Controller;
use QOR\App\Http\Requests\Api\AdminV1\RegisterPromoterRequest;
use QOR\App\Http\Requests\Api\AdminV1\UpdatePromoterProfileRequest;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\PromoterModel;

class PromoterController extends Controller
{
    public function __construct(
        private readonly RegisterPromoter $registerPromoter,
        private readonly EditPromoterProfile $editPromoterProfile,
        private readonly PromoterRepository $promoters,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        /** @var AdminUserModel $admin */
        $admin = $request->user();

        $promoterModel = PromoterModel::where('user_id', $admin->id)->first();
        if ($promoterModel === null) {
            return response()->json(['message' => 'Promoter não encontrado.'], 404);
        }

        $promoter = $this->promoters->findById((int) $promoterModel->id);
        if ($promoter === null) {
            return response()->json(['message' => 'Promoter não encontrado.'], 404);
        }

        return response()->json(['data' => $this->promoterToArray($promoter)]);
    }

    public function register(RegisterPromoterRequest $request): JsonResponse
    {
        /** @var string $policyVersion */
        $policyVersion = config('qor.legal.policy_version');

        $promoter = $this->registerPromoter->execute(
            name: $this->field($request, 'name'),
            contactPhone: $this->field($request, 'contact_phone'),
            contactEmail: $this->field($request, 'contact_email'),
            instagram: $this->nullableField($request, 'instagram'),
            tiktok: $this->nullableField($request, 'tiktok'),
            registrationEmail: $this->field($request, 'registration_email'),
            password: $this->field($request, 'password'),
            consentPolicyVersion: $policyVersion,
        );

        return response()->json(['data' => $this->promoterToArray($promoter)], 201);
    }

    public function update(UpdatePromoterProfileRequest $request): JsonResponse
    {
        /** @var AdminUserModel $admin */
        $admin = $request->user();

        $promoterModel = PromoterModel::where('user_id', $admin->id)->first();
        if ($promoterModel === null) {
            return response()->json(['message' => 'Promoter não encontrado.'], 404);
        }

        $promoter = $this->editPromoterProfile->execute(
            promoterId: (int) $promoterModel->id,
            name: $this->nullableField($request, 'name'),
            contactPhone: $this->nullableField($request, 'contact_phone'),
            contactEmail: $this->nullableField($request, 'contact_email'),
            instagram: $this->nullableField($request, 'instagram'),
            tiktok: $this->nullableField($request, 'tiktok'),
        );

        return response()->json(['data' => $this->promoterToArray($promoter)]);
    }

    private function field(FormRequest $request, string $key): string
    {
        /** @var string $value */
        $value = $request->validated($key);

        return $value;
    }

    private function nullableField(FormRequest $request, string $key): ?string
    {
        /** @var string|null $value */
        $value = $request->validated($key);

        return $value;
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
            'approval_status' => $promoter->approvalStatus->value,
        ];
    }
}
