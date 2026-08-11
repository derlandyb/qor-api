<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Venue extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'image_url', 'description', 'address', 'city',
        'latitude', 'longitude', 'contact_phone', 'contact_email',
        'social_links', 'verification_status',
    ];

    protected $casts = [
        'social_links' => 'array',
        'verification_status' => VerificationStatus::class,
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
