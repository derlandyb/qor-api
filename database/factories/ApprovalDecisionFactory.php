<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use QOR\App\Domain\Approval\Enum\ApprovalDecidableType;
use QOR\App\Domain\Approval\Enum\ApprovalOutcome;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\ApprovalDecisionModel;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\QOR\App\Infrastructure\Persistence\Eloquent\ApprovalDecisionModel>
 */
class ApprovalDecisionFactory extends Factory
{
    protected $model = ApprovalDecisionModel::class;

    public function definition(): array
    {
        return [
            'decidable_type' => ApprovalDecidableType::Venue->value,
            'decidable_id' => 1,
            'outcome' => ApprovalOutcome::Approved->value,
            'reason' => null,
            'decided_by' => AdminUserModel::factory(),
            'decided_at' => now(),
        ];
    }
}
