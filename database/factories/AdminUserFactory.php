<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use QOR\App\Models\AdminUser;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\QOR\App\Models\AdminUser>
 */
class AdminUserFactory extends Factory
{
    protected $model = AdminUser::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_super_admin' => false,
        ];
    }
}
