<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Promoter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'image_url', 'description', 'social_links',
        'contact_phone', 'contact_email', 'verification_status', 'user_id',
    ];

    protected $casts = [
        'social_links' => 'array',
        'verification_status' => VerificationStatus::class,
    ];

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_promoter');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verificationApplications(): HasMany
    {
        return $this->hasMany(VerificationApplication::class);
    }
}
