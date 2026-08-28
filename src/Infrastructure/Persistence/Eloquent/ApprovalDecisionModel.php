<?php

namespace QOR\App\Infrastructure\Persistence\Eloquent;

use Database\Factories\ApprovalDecisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use QOR\App\Domain\Approval\Enum\ApprovalDecidableType;
use QOR\App\Domain\Approval\Enum\ApprovalOutcome;

/**
 * @property-read ApprovalDecidableType $decidable_type
 * @property-read ApprovalOutcome $outcome
 * @property-read \Illuminate\Support\Carbon $decided_at
 */
class ApprovalDecisionModel extends Model
{
    /** @use HasFactory<ApprovalDecisionFactory> */
    use HasFactory;

    protected $table = 'approval_decisions';

    public $timestamps = false;

    protected $fillable = [
        'decidable_type',
        'decidable_id',
        'outcome',
        'reason',
        'decided_by',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decidable_type' => ApprovalDecidableType::class,
            'outcome' => ApprovalOutcome::class,
            'decided_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ApprovalDecisionFactory
    {
        return ApprovalDecisionFactory::new();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<AdminUserModel, $this>
     */
    public function decidedByAdmin(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AdminUserModel::class, 'decided_by');
    }
}
