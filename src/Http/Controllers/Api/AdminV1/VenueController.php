<?php

namespace QOR\App\Http\Controllers\Api\AdminV1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\Shared\UploadableFile;
use QOR\App\Domain\Venue\UseCase\EditVenueProfile;
use QOR\App\Domain\Venue\UseCase\RegisterVenue;
use QOR\App\Domain\Venue\Venue;
use QOR\App\Http\Controllers\Controller;
use QOR\App\Http\Requests\Api\AdminV1\RegisterVenueRequest;
use QOR\App\Http\Requests\Api\AdminV1\UpdateVenueProfileRequest;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\VenueModel;

class VenueController extends Controller
{
    public function __construct(
        private readonly RegisterVenue $registerVenue,
        private readonly EditVenueProfile $editVenueProfile,
    ) {
    }

    public function register(RegisterVenueRequest $request): JsonResponse
    {
        /** @var string $policyVersion */
        $policyVersion = config('qor.legal.policy_version');

        $venue = $this->registerVenue->execute(
            name: $this->field($request, 'name'),
            description: $this->field($request, 'description'),
            address: $this->field($request, 'address'),
            city: City::from($this->field($request, 'city')),
            contactPhone: $this->field($request, 'contact_phone'),
            contactEmail: $this->field($request, 'contact_email'),
            registrationEmail: $this->field($request, 'registration_email'),
            password: $this->field($request, 'password'),
            consentPolicyVersion: $policyVersion,
        );

        return response()->json(['data' => $this->venueToArray($venue)], 201);
    }

    public function update(UpdateVenueProfileRequest $request): JsonResponse
    {
        /** @var AdminUserModel $admin */
        $admin = $request->user();

        $venueModel = VenueModel::where('venue_admin_user_id', $admin->id)->first();
        if ($venueModel === null) {
            return response()->json(['message' => 'Venue não encontrada.'], 404);
        }

        $image = null;
        if ($request->hasFile('image')) {
            /** @var UploadedFile $file */
            $file = $request->file('image');
            $image = $this->toUploadableFile($file);
        }

        /** @var string|null $city */
        $city = $request->validated('city');

        $venue = $this->editVenueProfile->execute(
            venueId: (int) $venueModel->id,
            name: $this->nullableField($request, 'name'),
            description: $this->nullableField($request, 'description'),
            address: $this->nullableField($request, 'address'),
            city: $city !== null ? City::from($city) : null,
            contactPhone: $this->nullableField($request, 'contact_phone'),
            contactEmail: $this->nullableField($request, 'contact_email'),
            image: $image,
        );

        return response()->json(['data' => $this->venueToArray($venue)]);
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
    private function venueToArray(Venue $venue): array
    {
        return [
            'id' => $venue->id,
            'name' => $venue->name,
            'description' => $venue->description,
            'address' => $venue->address,
            'city' => $venue->city->value,
            'contact_phone' => $venue->contactPhone,
            'contact_email' => $venue->contactEmail,
            'approval_status' => $venue->approvalStatus->value,
            'image_url' => $venue->imageUrl,
        ];
    }
}
