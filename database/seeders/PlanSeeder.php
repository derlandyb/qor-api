<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('plans')->updateOrInsert(
            ['is_default_free' => true],
            [
                'name' => 'Gratuito',
                'monthly_price' => 0,
                'annual_price' => null,
                'publish_quota' => config('qor.billing.default_free_quota'),
                'is_active' => true,
                'is_default_free' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
}
