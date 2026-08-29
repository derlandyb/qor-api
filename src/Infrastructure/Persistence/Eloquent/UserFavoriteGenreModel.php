<?php

namespace QOR\App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class UserFavoriteGenreModel extends Model
{
    protected $table = 'user_favorite_genres';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'genre_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
