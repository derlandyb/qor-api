<?php

namespace QOR\App\Infrastructure\Persistence\Eloquent;

use Database\Factories\FriendshipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use QOR\App\Domain\Social\Enum\FriendshipStatus;

/**
 * @property-read FriendshipStatus $status
 */
class FriendshipModel extends Model
{
    /** @use HasFactory<FriendshipFactory> */
    use HasFactory;

    protected $table = 'friendships';

    protected $fillable = [
        'requester_id',
        'recipient_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => FriendshipStatus::class,
        ];
    }

    protected static function newFactory(): FriendshipFactory
    {
        return FriendshipFactory::new();
    }
}
