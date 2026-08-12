<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\VerificationApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\VerificationApplicationResource;
use App\Models\VerificationApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class VerificationReviewController extends Controller
{
    /**
     * moderation's concurrency-safety amendment (spec's Edge Case: two admins must not conflict
     * on the same item). Row lock closes the race window; VerificationApplication::approve()'s
     * own abort_unless() still produces the 409 on a non-pending application, unchanged.
     */
    public function approve(VerificationApplication $application, Request $request): JsonResponse
    {
        DB::transaction(function () use ($application, $request) {
            VerificationApplication::lockForUpdate()->findOrFail($application->id)->approve($request->user());
        });

        return (new VerificationApplicationResource($application->fresh()))->response();
    }

    public function reject(VerificationApplication $application, Request $request): JsonResponse
    {
        $feedback = strip_tags($request->validate(['feedback' => ['required', 'string', 'max:2000']])['feedback']);

        DB::transaction(function () use ($application, $request, $feedback) {
            VerificationApplication::lockForUpdate()->findOrFail($application->id)
                ->reject($request->user(), $feedback);
        });

        return (new VerificationApplicationResource($application->fresh()))->response();
    }

    public function index(Request $request): JsonResponse
    {
        $query = VerificationApplication::query()->latest();

        if ($request->filled('status')) {
            $query->where('status', VerificationApplicationStatus::from($request->string('status')->toString()));
        }

        return VerificationApplicationResource::collection($query->get())->response();
    }
}
