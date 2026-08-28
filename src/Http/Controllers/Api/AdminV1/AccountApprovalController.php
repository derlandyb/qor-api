<?php

namespace QOR\App\Http\Controllers\Api\AdminV1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use QOR\App\Domain\Approval\ApprovalDecision;
use QOR\App\Domain\Approval\ApprovalDecisionRepository;
use QOR\App\Domain\Approval\Enum\ApprovalDecidableType;
use QOR\App\Domain\Approval\Enum\ApprovalOutcome;
use QOR\App\Domain\Approval\PendingAccount;
use QOR\App\Domain\Approval\UseCase\DecideAccountApproval;
use QOR\App\Domain\Promoter\Promoter;
use QOR\App\Domain\Promoter\PromoterRepository;
use QOR\App\Domain\Venue\Venue;
use QOR\App\Domain\Venue\VenueRepository;
use QOR\App\Http\Controllers\Controller;
use QOR\App\Http\Requests\Api\AdminV1\DecideApprovalRequest;
use ValueError;

class AccountApprovalController extends Controller
{
    public function __construct(
        private readonly ApprovalDecisionRepository $approvalDecisionRepository,
        private readonly VenueRepository $venueRepository,
        private readonly PromoterRepository $promoterRepository,
        private readonly DecideAccountApproval $decideAccountApproval,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $pending = $this->approvalDecisionRepository->findPendingAccounts();

        $total = count($pending);
        /** @var int $perPage */
        $perPage = config('qor.pagination.admin_page_size');

        $page = max(1, (int) $request->query('page', 1));

        $pageItems = array_slice($pending, ($page - 1) * $perPage, $perPage);

        $data = array_values(array_filter(array_map(
            fn (PendingAccount $account) => $this->pendingAccountToArray($account),
            $pageItems,
        )));

        return response()->json([
            'data' => $data,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
        ]);
    }

    public function decide(DecideApprovalRequest $request, string $accountType, int $id): JsonResponse
    {
        try {
            $type = ApprovalDecidableType::from($accountType);
        } catch (ValueError) {
            return response()->json(['message' => 'Tipo de conta inválido.'], 404);
        }

        /** @var string $outcomeValue */
        $outcomeValue = $request->validated('outcome');

        /** @var string|null $reason */
        $reason = $request->validated('reason');

        /** @var int $decidedBy */
        $decidedBy = Auth::guard('admin')->id();

        $decision = $this->decideAccountApproval->execute(
            accountType: $type,
            accountId: $id,
            outcome: ApprovalOutcome::from($outcomeValue),
            reason: $reason,
            decidedBy: $decidedBy,
        );

        return response()->json(['data' => $this->decisionToArray($decision)]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pendingAccountToArray(PendingAccount $account): ?array
    {
        if ($account->type === ApprovalDecidableType::Venue) {
            $venue = $this->venueRepository->findById($account->id);

            return $venue === null ? null : $this->venueToArray($venue);
        }

        $promoter = $this->promoterRepository->findById($account->id);

        return $promoter === null ? null : $this->promoterToArray($promoter);
    }

    /**
     * @return array<string, mixed>
     */
    private function venueToArray(Venue $venue): array
    {
        return [
            'id' => $venue->id,
            'type' => ApprovalDecidableType::Venue->value,
            'name' => $venue->name,
            'contact_email' => $venue->contactEmail,
            'contact_phone' => $venue->contactPhone,
            'city' => $venue->city->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function promoterToArray(Promoter $promoter): array
    {
        return [
            'id' => $promoter->id,
            'type' => ApprovalDecidableType::Promoter->value,
            'name' => $promoter->name,
            'contact_email' => $promoter->contactEmail,
            'contact_phone' => $promoter->contactPhone,
            'instagram' => $promoter->instagram,
            'tiktok' => $promoter->tiktok,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decisionToArray(ApprovalDecision $decision): array
    {
        return [
            'id' => $decision->id,
            'decidable_type' => $decision->decidableType->value,
            'decidable_id' => $decision->decidableId,
            'outcome' => $decision->outcome->value,
            'reason' => $decision->reason,
            'decided_by' => $decision->decidedBy,
            'decided_at' => $decision->decidedAt->format(DATE_ATOM),
        ];
    }
}
