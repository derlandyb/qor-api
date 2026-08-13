<?php

namespace App\Http\Controllers\Api\Publisher;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Publisher\VenueOptionResource;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The venue-selector data source EventForm needs (design.md's "searchable Venue select for
 * Promoter accounts, locked/pre-filled value for Venue accounts") — no such listing existed
 * anywhere else in the app; the only prior venue listing (ModerationQueueController) is
 * admin-gated. Sits inside the same auth:sanctum + can:manage-events group as every other
 * publisher route, so a Venue-type account here is always already Verified.
 */
final class VenueOptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->venueProfile !== null) {
            return response()->json([
                'data' => VenueOptionResource::collection([$user->venueProfile])->resolve(),
                'accountType' => 'venue',
            ]);
        }

        $venues = Venue::query()
            ->where('verification_status', VerificationStatus::Verified)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => VenueOptionResource::collection($venues)->resolve(),
            'accountType' => 'promoter',
        ]);
    }
}
