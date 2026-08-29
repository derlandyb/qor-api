<?php

namespace QOR\App\Infrastructure\Persistence\Eloquent;

use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use QOR\App\Domain\Billing\Enum\BillingCycle;
use QOR\App\Domain\Billing\Enum\SubscribableType;
use QOR\App\Domain\Billing\Enum\SubscriptionStatus;

/**
 * @property-read SubscribableType $subscribable_type
 * @property-read SubscriptionStatus $status
 * @property-read ?BillingCycle $billing_cycle
 * @property-read \Illuminate\Support\Carbon $current_period_start
 * @property-read \Illuminate\Support\Carbon $current_period_end
 */
class SubscriptionModel extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    protected $table = 'subscriptions';

    protected $fillable = [
        'subscribable_type',
        'subscribable_id',
        'plan_id',
        'status',
        'billing_cycle',
        'current_period_start',
        'current_period_end',
        'publishes_used_this_period',
    ];

    protected function casts(): array
    {
        return [
            'subscribable_type' => SubscribableType::class,
            'status' => SubscriptionStatus::class,
            'billing_cycle' => BillingCycle::class,
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'publishes_used_this_period' => 'integer',
        ];
    }

    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<PlanModel, $this>
     */
    public function plan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PlanModel::class, 'plan_id');
    }
}
