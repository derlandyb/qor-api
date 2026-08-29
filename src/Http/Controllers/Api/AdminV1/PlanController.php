<?php

namespace QOR\App\Http\Controllers\Api\AdminV1;

use Illuminate\Http\JsonResponse;
use QOR\App\Domain\Billing\Plan;
use QOR\App\Domain\Billing\UseCase\CreatePlan;
use QOR\App\Domain\Billing\UseCase\DeactivatePlan;
use QOR\App\Domain\Billing\UseCase\ListAllPlans;
use QOR\App\Domain\Billing\UseCase\UpdatePlan;
use QOR\App\Http\Controllers\Controller;
use QOR\App\Http\Requests\Api\AdminV1\CreatePlanRequest;
use QOR\App\Http\Requests\Api\AdminV1\UpdatePlanRequest;

class PlanController extends Controller
{
    public function __construct(
        private readonly ListAllPlans $listAllPlans,
        private readonly CreatePlan $createPlan,
        private readonly UpdatePlan $updatePlan,
        private readonly DeactivatePlan $deactivatePlan,
    ) {
    }

    public function index(): JsonResponse
    {
        $plans = $this->listAllPlans->execute();

        return response()->json([
            'data' => array_map(fn (Plan $plan) => $this->planToArray($plan), $plans),
        ]);
    }

    public function store(CreatePlanRequest $request): JsonResponse
    {
        /** @var string $name */
        $name = $request->validated('name');
        /** @var float $monthlyPrice */
        $monthlyPrice = $request->validated('monthly_price');
        /** @var float|null $annualPrice */
        $annualPrice = $request->validated('annual_price');
        /** @var int $publishQuota */
        $publishQuota = $request->validated('publish_quota');

        $plan = $this->createPlan->execute(
            name: $name,
            monthlyPrice: (float) $monthlyPrice,
            publishQuota: (int) $publishQuota,
            annualPrice: $annualPrice !== null ? (float) $annualPrice : null,
        );

        return response()->json(['data' => $this->planToArray($plan)], 201);
    }

    public function update(UpdatePlanRequest $request, int $id): JsonResponse
    {
        /** @var string $name */
        $name = $request->validated('name');
        /** @var float $monthlyPrice */
        $monthlyPrice = $request->validated('monthly_price');
        /** @var float|null $annualPrice */
        $annualPrice = $request->validated('annual_price');
        /** @var int $publishQuota */
        $publishQuota = $request->validated('publish_quota');

        $plan = $this->updatePlan->execute(
            planId: $id,
            name: $name,
            monthlyPrice: (float) $monthlyPrice,
            publishQuota: (int) $publishQuota,
            annualPrice: $annualPrice !== null ? (float) $annualPrice : null,
        );

        return response()->json(['data' => $this->planToArray($plan)]);
    }

    public function deactivate(int $id): JsonResponse
    {
        $plan = $this->deactivatePlan->execute($id);

        return response()->json(['data' => $this->planToArray($plan)]);
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
            'is_active' => $plan->isActive,
            'is_default_free' => $plan->isDefaultFree,
        ];
    }
}
