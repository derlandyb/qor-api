<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PromoterResource;
use App\Http\Resources\VenueResource;
use App\Models\Promoter;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;

final class VerificationRevocationController extends Controller
{
    // Deliberately the only write — no Event table write, no cascading anything. Already-
    // published events stay exactly as they are (VERIFY-005); only future event management
    // is blocked, via the separately-defined `manage-events` gate reading this same column.
    public function revokePromoter(Promoter $promoter): JsonResponse
    {
        $promoter->update(['verification_status' => VerificationStatus::Unverified]);

        return (new PromoterResource($promoter))->response();
    }

    public function revokeVenue(Venue $venue): JsonResponse
    {
        $venue->update(['verification_status' => VerificationStatus::Unverified]);

        return (new VenueResource($venue))->response();
    }
}
