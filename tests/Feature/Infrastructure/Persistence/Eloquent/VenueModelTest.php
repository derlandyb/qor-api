<?php

namespace Tests\Feature\Infrastructure\Persistence\Eloquent;

use Illuminate\Foundation\Testing\RefreshDatabase;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\VenueModel;
use Tests\TestCase;

class VenueModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_a_venue_WHEN_loading_the_venueAdmin_relation_THEN_it_resolves_the_owning_admin(): void
    {
        $admin = AdminUserModel::factory()->create();
        $venue = VenueModel::factory()->create(['venue_admin_user_id' => $admin->id]);

        $this->assertSame($admin->id, $venue->venueAdmin->id);
    }
}
