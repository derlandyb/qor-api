<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Infrastructure\Persistence\Eloquent\VenueModel;

class VenueSeeder extends Seeder
{
    public function run(): void
    {
        foreach (City::cases() as $city) {
            VenueModel::factory()
                ->approved()
                ->count(2)
                ->create(['city' => $city->value]);

            VenueModel::factory()->create(['city' => $city->value]);
        }
    }
}
