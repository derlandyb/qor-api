<?php

namespace QOR\App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class GenreModel extends Model
{
    protected $table = 'genres';

    protected $fillable = [
        'name',
        'slug',
    ];
}
