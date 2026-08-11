<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// Minimal supporting entity — not a formally owned Spec ID (no ARTIST-001 exists yet),
// modeled only enough to satisfy the Event relationship and the "missing artist" edge case.
final class Artist extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'image_url', 'genres', 'social_links'];

    protected $casts = [
        'genres' => 'array',
        'social_links' => 'array',
    ];

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_artist');
    }
}
