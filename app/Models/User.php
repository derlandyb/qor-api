<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\Role;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'account_type',
        'role',
        'must_change_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'account_type' => AccountType::class,
        'role' => Role::class,
        'must_change_password' => 'boolean',
    ];

    public function favoriteEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'favorites')->withTimestamps();
    }

    public function promoterProfile(): HasOne
    {
        return $this->hasOne(Promoter::class);
    }

    public function venueProfile(): HasOne
    {
        return $this->hasOne(Venue::class);
    }

    public function verificationApplications(): HasMany
    {
        return $this->hasMany(VerificationApplication::class);
    }

    /** The first concrete definition of "is this account an admin" (moderation's `can:admin` gate). */
    public function isAdmin(): bool
    {
        return in_array($this->role, [Role::Admin, Role::SuperAdmin], true);
    }
}
