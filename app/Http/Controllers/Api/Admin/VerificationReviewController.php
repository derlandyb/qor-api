<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\VerificationApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\VerificationApplicationResource;
use App\Models\VerificationApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VerificationReviewController extends Controller
{
    public function approve(VerificationApplication $application, Request $request): JsonResponse
    {
        // The model's own abort_unless() produces the 409 on a non-pending application.
        $application->approve($request->user());

        return (new VerificationApplicationResource($application->fresh()))->response();
    }

    public function reject(VerificationApplication $application, Request $request): JsonResponse
    {
        $request->validate(['feedback' => ['required', 'string']]);

        $application->reject($request->user(), $request->input('feedback'));

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
