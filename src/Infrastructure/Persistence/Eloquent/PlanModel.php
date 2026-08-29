<?php

namespace QOR\App\Infrastructure\Persistence\Eloquent;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanModel extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $table = 'plans';

    protected $fillable = [
        'name',
        'monthly_price',
        'annual_price',
        'publish_quota',
        'is_active',
        'is_default_free',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'float',
            'annual_price' => 'float',
            'publish_quota' => 'integer',
            'is_active' => 'boolean',
            'is_default_free' => 'boolean',
        ];
    }

    protected static function newFactory(): PlanFactory
    {
        return PlanFactory::new();
    }
}
