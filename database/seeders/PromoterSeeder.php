<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use QOR\App\Infrastructure\Persistence\Eloquent\PromoterModel;

class PromoterSeeder extends Seeder
{
    public function run(): void
    {
        PromoterModel::factory()->approved()->count(6)->create();
        PromoterModel::factory()->count(2)->create();
    }
}
