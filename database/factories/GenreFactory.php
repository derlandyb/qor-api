<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use QOR\App\Infrastructure\Persistence\Eloquent\GenreModel;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<GenreModel>
 */
class GenreFactory extends Factory
{
    protected $model = GenreModel::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
        ];
    }
}
