<?php

namespace QOR\App\Infrastructure\Persistence\Eloquent;

use Database\Factories\PromoterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;

/**
 * @property-read ApprovalStatus $approval_status
 */
class PromoterModel extends Model
{
    /** @use HasFactory<PromoterFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'promoters';

    protected $fillable = [
        'user_id',
        'name',
        'contact_phone',
        'contact_email',
        'instagram',
        'tiktok',
        'approval_status',
    ];

    protected function casts(): array
    {
        return [
            'approval_status' => ApprovalStatus::class,
        ];
    }

    protected static function newFactory(): PromoterFactory
    {
        return PromoterFactory::new();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<AdminUserModel, $this>
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AdminUserModel::class, 'user_id');
    }
}
