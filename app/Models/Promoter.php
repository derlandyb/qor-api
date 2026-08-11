<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Promoter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'image_url', 'description', 'social_links',
        'contact_phone', 'contact_email', 'verification_status',
    ];

    protected $casts = [
        'social_links' => 'array',
        'verification_status' => VerificationStatus::class,
    ];

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_promoter');
    }
}
