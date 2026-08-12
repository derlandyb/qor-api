<?php

namespace App\Http\Controllers\Api;

use App\Enums\AccountType;
use App\Enums\VerificationApplicationStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\VerificationApplicationRequest;
use App\Http\Resources\VerificationApplicationResource;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use App\Models\VerificationApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class VerificationApplicationController extends Controller
{
    public function store(VerificationApplicationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        if ($user->verificationApplications()->where('status', VerificationApplicationStatus::PendingReview)->exists()) {
            return response()->json(['message' => 'Já existe uma aplicação pendente de análise.'], 409);
        }

        $accountType = AccountType::from($validated['account_type']);
        $applicantProfile = $this->applicantProfile($user, $accountType, $validated['business_name']);

        $documentPath = null;
        if ($request->hasFile('document')) {
            try {
                $documentPath = $request->file('document')->store('verification-documents');
            } catch (Throwable $e) {
                // Storage failure never blocks the rest of the submission — the applicant
                // can still be reviewed on the details provided; document_path stays null.
                Log::warning('Verification document upload failed', ['exception' => $e]);
            }
        }

        $application = VerificationApplication::query()->create([
            'user_id' => $user->id,
            'account_type' => $accountType,
            'promoter_id' => $applicantProfile instanceof Promoter ? $applicantProfile->id : null,
            'venue_id' => $applicantProfile instanceof Venue ? $applicantProfile->id : null,
            'business_name' => $validated['business_name'],
            'contact_email' => $validated['contact_email'],
            'contact_phone' => $validated['contact_phone'] ?? null,
            'social_link' => $validated['social_link'],
            'document_path' => $documentPath,
            'status' => VerificationApplicationStatus::PendingReview,
        ]);

        $applicantProfile->update(['verification_status' => VerificationStatus::PendingReview]);

        return (new VerificationApplicationResource($application))->response()->setStatusCode(201);
    }

    public function status(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        // Queried fresh (not via the cached `promoterProfile`/`venueProfile` accessor) — this
        // guards against a stale null cached earlier in the same object's lifetime, e.g. right
        // after `store()` lazily created the profile on this very request/model instance.
        $profile = $user->promoterProfile()->first() ?? $user->venueProfile()->first();

        if ($profile === null) {
            return response()->json([
                'status' => VerificationStatus::Unverified->value,
                'rejectionFeedback' => null,
                'latestApplication' => null,
            ]);
        }

        $latestApplication = $user->verificationApplications()->latest()->first();

        $rejectionFeedback = null;
        if ($profile->verification_status === VerificationStatus::Unverified
            && $latestApplication?->status === VerificationApplicationStatus::Rejected) {
            $rejectionFeedback = $latestApplication->rejection_feedback;
        }

        return response()->json([
            'status' => $profile->verification_status->value,
            'rejectionFeedback' => $rejectionFeedback,
            'latestApplication' => $latestApplication ? new VerificationApplicationResource($latestApplication) : null,
        ]);
    }

    /** Lazily creates the Promoter/Venue skeleton row a first-time applicant doesn't have yet. */
    private function applicantProfile(User $user, AccountType $accountType, string $businessName): Promoter|Venue
    {
        if ($accountType === AccountType::Promoter) {
            return $user->promoterProfile()->first()
                ?? Promoter::query()->create(['name' => $businessName, 'user_id' => $user->id]);
        }

        // 'city' has no NOT NULL default and isn't collected on this form — left blank
        // pending the venue filling in its full profile separately (out of this feature's scope).
        return $user->venueProfile()->first()
            ?? Venue::query()->create(['name' => $businessName, 'city' => '', 'user_id' => $user->id]);
    }
}
