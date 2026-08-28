<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;

class FanSeeder extends Seeder
{
    public function run(): void
    {
        UserModel::factory()->count(20)->create();
    }
}
