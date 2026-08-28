<?php

namespace Tests\Feature\Infrastructure\Persistence\Eloquent;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use QOR\App\Infrastructure\Persistence\Eloquent\EventModel;
use QOR\App\Infrastructure\Persistence\Eloquent\PromoterModel;
use Tests\TestCase;

class EventModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_a_tagged_promoter_WHEN_loading_the_promoters_relation_THEN_it_is_returned(): void
    {
        $genreId = DB::table('genres')->insertGetId(['name' => 'Rock', 'slug' => 'rock', 'created_at' => now(), 'updated_at' => now()]);
        $event = EventModel::factory()->create(['genre_id' => $genreId]);
        $promoter = PromoterModel::factory()->create();

        $event->promoters()->attach($promoter->id, ['tagged_at' => now()]);

        $this->assertTrue($event->promoters()->exists());
        $this->assertSame($promoter->id, $event->promoters()->first()->id);
    }
}
