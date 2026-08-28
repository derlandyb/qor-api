<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenreSeeder extends Seeder
{
    public function run(): void
    {
        $genres = [
            'Rock',
            'Samba',
            'Sertanejo',
            'Eletrônico',
            'Reggae',
            'Pagode',
            'MPB',
            'Funk',
            'Forró',
            'Hip Hop',
        ];

        foreach ($genres as $name) {
            DB::table('genres')->updateOrInsert(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }
}
