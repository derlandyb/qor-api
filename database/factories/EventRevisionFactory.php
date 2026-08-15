<?php

namespace Database\Factories;

use App\Enums\EventRevisionStatus;
use App\Models\Event;
use App\Models\EventRevision;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventRevision>
 */
class EventRevisionFactory extends Factory
{
    protected $model = EventRevision::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'cover_image_path' => 'events/covers/'.fake()->uuid().'.jpg',
            'start_date_time' => fake()->dateTimeBetween('+1 day', '+2 months'),
            'end_date_time' => null,
            'venue_id' => Venue::factory(),
            'price_min' => fake()->randomFloat(2, 20, 50),
            'price_max' => fake()->randomFloat(2, 50, 150),
            'is_free' => false,
            'currency' => 'BRL',
            'genres' => fake()->randomElements(['rock', 'mpb', 'samba', 'forró', 'reggae', 'pop'], 2),
            'ticket_url' => fake()->url(),
            'status' => EventRevisionStatus::PendingApproval,
        ];
    }
}
