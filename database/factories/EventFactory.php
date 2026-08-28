<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use QOR\App\Domain\Event\Enum\EventCreatedByType;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Infrastructure\Persistence\Eloquent\EventModel;
use QOR\App\Infrastructure\Persistence\Eloquent\VenueModel;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\QOR\App\Infrastructure\Persistence\Eloquent\EventModel>
 */
class EventFactory extends Factory
{
    protected $model = EventModel::class;

    public function definition(): array
    {
        $isFree = fake()->boolean();

        /** @var City $city */
        $city = fake()->randomElement(City::cases());

        return [
            'created_by_type' => EventCreatedByType::VenueAdmin->value,
            'created_by_id' => VenueModel::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'cover_image_url' => fake()->imageUrl(),
            'starts_at' => fake()->dateTimeBetween('now', '+3 months'),
            'city' => $city->value,
            'genre_id' => fn () => DB::table('genres')->inRandomOrder()->value('id')
                ?? DB::table('genres')->insertGetId(['name' => 'Rock', 'slug' => 'rock', 'created_at' => now(), 'updated_at' => now()]),
            'is_free' => $isFree,
            'ticket_url' => $isFree ? null : fake()->url(),
            'status' => EventStatus::Draft->value,
        ];
    }

    public function pendingReview(): static
    {
        return $this->state(fn (array $attributes) => ['status' => EventStatus::PendingReview->value]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => ['status' => EventStatus::Published->value]);
    }
}
