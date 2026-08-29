<?php

namespace QOR\App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use QOR\App\Domain\Billing\Plan;
use QOR\App\Domain\Billing\UseCase\ListActivePlans;
use QOR\App\Http\Controllers\Controller;

class PlanController extends Controller
{
    public function __construct(
        private readonly ListActivePlans $listActivePlans,
    ) {
    }

    public function index(): JsonResponse
    {
        $plans = $this->listActivePlans->execute();

        return response()->json([
            'data' => array_map(fn (Plan $plan) => $this->planToArray($plan), $plans),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function planToArray(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'monthly_price' => $plan->monthlyPrice,
            'annual_price' => $plan->annualPrice,
            'publish_quota' => $plan->publishQuota,
        ];
    }
}
