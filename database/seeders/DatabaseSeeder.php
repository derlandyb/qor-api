<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);
        $this->call(GenreSeeder::class);
        $this->call(FanSeeder::class);
        $this->call(PlanSeeder::class);
        $this->call(VenueSeeder::class);
        $this->call(PromoterSeeder::class);
        $this->call(EventSeeder::class);
    }
}
