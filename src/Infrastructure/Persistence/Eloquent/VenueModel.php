<?php

namespace QOR\App\Infrastructure\Persistence\Eloquent;

use Database\Factories\VenueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Domain\Shared\Enum\City;

/**
 * @property-read City $city
 * @property-read ApprovalStatus $approval_status
 */
class VenueModel extends Model
{
    /** @use HasFactory<VenueFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'venues';

    protected $fillable = [
        'venue_admin_user_id',
        'name',
        'description',
        'address',
        'city',
        'contact_phone',
        'contact_email',
        'image_url',
        'approval_status',
    ];

    protected function casts(): array
    {
        return [
            'city' => City::class,
            'approval_status' => ApprovalStatus::class,
        ];
    }

    protected static function newFactory(): VenueFactory
    {
        return VenueFactory::new();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<AdminUserModel, $this>
     */
    public function venueAdmin(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AdminUserModel::class, 'venue_admin_user_id');
    }
}
