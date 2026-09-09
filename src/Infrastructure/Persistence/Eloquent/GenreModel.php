<?php

namespace QOR\App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read string $name
 */
class GenreModel extends Model
{
    protected $table = 'genres';

    protected $fillable = [
        'name',
        'slug',
    ];
}
