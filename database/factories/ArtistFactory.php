<?php

namespace Database\Factories;

use App\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Artist>
 */
class ArtistFactory extends Factory
{
    protected $model = Artist::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'image_url' => fake()->imageUrl(),
            'genres' => fake()->randomElements(['rock', 'mpb', 'samba', 'forró', 'reggae', 'pop'], 2),
            'social_links' => ['instagram' => fake()->userName()],
        ];
    }
}
