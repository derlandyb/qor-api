<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use QOR\App\Infrastructure\Persistence\Eloquent\PlanModel;
use Tests\TestCase;

class PlanControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_active_plans_exist_WHEN_listing_publicly_THEN_they_are_returned_with_name_price_and_quota(): void
    {
        PlanModel::factory()->create([
            'name' => 'Gratuito',
            'monthly_price' => 0,
            'publish_quota' => 5,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/plans');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'name', 'monthly_price', 'annual_price', 'publish_quota']]])
            ->assertJsonPath('data.0.name', 'Gratuito')
            ->assertJsonPath('data.0.monthly_price', 0)
            ->assertJsonPath('data.0.publish_quota', 5);
    }

    public function test_GIVEN_no_authentication_WHEN_listing_plans_THEN_it_succeeds(): void
    {
        PlanModel::factory()->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/plans');

        $response->assertStatus(200);
    }

    public function test_GIVEN_a_deactivated_plan_WHEN_listing_publicly_THEN_it_is_not_included(): void
    {
        PlanModel::factory()->create(['name' => 'Ativo', 'is_active' => true]);
        PlanModel::factory()->create(['name' => 'Inativo', 'is_active' => false]);

        $response = $this->getJson('/api/v1/plans');

        $response->assertStatus(200)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ativo');
    }
}
