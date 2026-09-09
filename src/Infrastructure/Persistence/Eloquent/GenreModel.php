<?php

namespace QOR\App\Infrastructure\Persistence\Eloquent;

use Database\Factories\GenreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read string $name
 */
class GenreModel extends Model
{
    /** @use HasFactory<GenreFactory> */
    use HasFactory;

    protected $table = 'genres';

    protected $fillable = [
        'name',
        'slug',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): GenreFactory
    {
        return GenreFactory::new();
    }
}
