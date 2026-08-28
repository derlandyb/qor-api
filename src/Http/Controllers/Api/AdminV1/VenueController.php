<?php

namespace QOR\App\Http\Controllers\Api\AdminV1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\Venue\UseCase\RegisterVenue;
use QOR\App\Domain\Venue\Venue;
use QOR\App\Http\Controllers\Controller;
use QOR\App\Http\Requests\Api\AdminV1\RegisterVenueRequest;

class VenueController extends Controller
{
    public function __construct(
        private readonly RegisterVenue $registerVenue,
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

    private function field(FormRequest $request, string $key): string
    {
        /** @var string $value */
        $value = $request->validated($key);

        return $value;
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
