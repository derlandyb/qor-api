<?php

namespace QOR\App\Http\Controllers\Api\AdminV1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use QOR\App\Domain\Billing\Enum\SubscribableType;
use QOR\App\Domain\Billing\UsageSummary;
use QOR\App\Domain\Billing\UseCase\GetOrganizerUsage;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Http\Controllers\Controller;
use QOR\App\Http\Support\OrganizerIdentityResolver;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly OrganizerIdentityResolver $organizerIdentityResolver,
        private readonly GetOrganizerUsage $getOrganizerUsage,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        /** @var AdminUserModel $admin */
        $admin = $request->user();

        [$createdByType, $createdById] = $this->organizerIdentityResolver->resolve($admin);

        $subscribableType = $createdByType === EventCreatedByType::VenueAdmin
            ? SubscribableType::Venue
            : SubscribableType::Promoter;

        $usage = $this->getOrganizerUsage->execute($subscribableType, $createdById);

        return response()->json(['data' => $this->usageToArray($usage)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function usageToArray(UsageSummary $usage): array
    {
        return [
            'plan_name' => $usage->planName,
            'monthly_price' => $usage->monthlyPrice,
            'publish_quota' => $usage->publishQuota,
            'publishes_used_this_period' => $usage->publishesUsedThisPeriod,
            'is_at_limit' => $usage->isAtLimit,
        ];
    }
}
