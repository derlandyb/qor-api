<?php

namespace Tests\Feature\Http\Controllers\Api\AdminV1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use QOR\App\Domain\Billing\Enum\SubscribableType;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\PlanModel;
use QOR\App\Infrastructure\Persistence\Eloquent\SubscriptionModel;
use QOR\App\Infrastructure\Persistence\Eloquent\VenueModel;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsVenueAdmin(): VenueModel
    {
        $admin = AdminUserModel::factory()->create();
        $venue = VenueModel::factory()->approved()->create(['venue_admin_user_id' => $admin->id]);

        Sanctum::actingAs($admin, ['*'], 'admin');

        return $venue;
    }

    public function test_GIVEN_an_organizer_with_3_of_5_used_WHEN_viewing_their_subscription_THEN_the_response_shows_3_of_5(): void
    {
        $venue = $this->actingAsVenueAdmin();
        $plan = PlanModel::factory()->create(['name' => 'Gratuito', 'publish_quota' => 5]);
        SubscriptionModel::factory()->create([
            'subscribable_type' => SubscribableType::Venue->value,
            'subscribable_id' => $venue->id,
            'plan_id' => $plan->id,
            'publishes_used_this_period' => 3,
        ]);

        $response = $this->getJson('/api/admin/v1/subscription');

        $response->assertStatus(200)
            ->assertJsonPath('data.plan_name', 'Gratuito')
            ->assertJsonPath('data.publish_quota', 5)
            ->assertJsonPath('data.publishes_used_this_period', 3)
            ->assertJsonPath('data.is_at_limit', false);
    }

    public function test_GIVEN_an_organizer_at_their_limit_WHEN_viewing_their_subscription_THEN_the_response_flags_at_limit(): void
    {
        $venue = $this->actingAsVenueAdmin();
        $plan = PlanModel::factory()->create(['publish_quota' => 5]);
        SubscriptionModel::factory()->create([
            'subscribable_type' => SubscribableType::Venue->value,
            'subscribable_id' => $venue->id,
            'plan_id' => $plan->id,
            'publishes_used_this_period' => 5,
        ]);

        $response = $this->getJson('/api/admin/v1/subscription');

        $response->assertStatus(200)->assertJsonPath('data.is_at_limit', true);
    }
}
