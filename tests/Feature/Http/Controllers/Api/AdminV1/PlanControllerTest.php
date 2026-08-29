<?php

namespace Tests\Feature\Http\Controllers\Api\AdminV1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\PlanModel;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\TestCase;

class PlanControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): AdminUserModel
    {
        $admin = AdminUserModel::factory()->superAdmin()->create();

        Sanctum::actingAs($admin, ['*'], 'admin');

        return $admin;
    }

    public function test_GIVEN_valid_plan_data_WHEN_a_super_admin_creates_a_plan_THEN_it_is_created_and_visible_publicly(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/admin/v1/plans', [
            'name' => 'Profissional',
            'monthly_price' => 99.90,
            'publish_quota' => 20,
        ]);

        $response->assertStatus(201)->assertJsonPath('data.name', 'Profissional');

        $publicResponse = $this->getJson('/api/v1/plans');
        $publicResponse->assertStatus(200)->assertJsonPath('data.0.name', 'Profissional');
    }

    public function test_GIVEN_active_and_inactive_plans_WHEN_a_super_admin_lists_all_plans_THEN_the_response_includes_each_plans_fields(): void
    {
        $this->actingAsSuperAdmin();
        PlanModel::factory()->create([
            'name' => 'Gratuito',
            'monthly_price' => 0,
            'publish_quota' => 5,
            'is_active' => true,
        ]);
        PlanModel::factory()->create([
            'name' => 'Descontinuado',
            'monthly_price' => 29.9,
            'publish_quota' => 10,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/admin/v1/plans');

        $response->assertStatus(200)->assertJsonCount(2, 'data');
        $byName = collect($response->json('data'))->keyBy('name');
        $this->assertEquals(0, $byName['Gratuito']['monthly_price']);
        $this->assertSame(5, $byName['Gratuito']['publish_quota']);
        $this->assertTrue($byName['Gratuito']['is_active']);
        $this->assertEquals(29.9, $byName['Descontinuado']['monthly_price']);
        $this->assertSame(10, $byName['Descontinuado']['publish_quota']);
        $this->assertFalse($byName['Descontinuado']['is_active']);
    }

    public function test_GIVEN_a_missing_required_field_WHEN_creating_a_plan_THEN_it_returns_a_field_specific_error(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/admin/v1/plans', [
            'monthly_price' => 99.90,
            'publish_quota' => 20,
        ]);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors' => ['name']]);
    }

    public function test_GIVEN_an_existing_plan_WHEN_a_super_admin_updates_its_quota_THEN_the_change_applies_going_forward(): void
    {
        $this->actingAsSuperAdmin();
        $plan = PlanModel::factory()->create(['publish_quota' => 5]);

        $response = $this->patchJson("/api/admin/v1/plans/{$plan->id}", [
            'name' => $plan->name,
            'monthly_price' => $plan->monthly_price,
            'publish_quota' => 15,
        ]);

        $response->assertStatus(200)->assertJsonPath('data.publish_quota', 15);
        $this->assertDatabaseHas('plans', ['id' => $plan->id, 'publish_quota' => 15]);
    }

    public function test_GIVEN_an_existing_plan_WHEN_a_super_admin_deactivates_it_THEN_it_disappears_from_the_public_list(): void
    {
        $this->actingAsSuperAdmin();
        $plan = PlanModel::factory()->create(['is_active' => true]);

        $response = $this->postJson("/api/admin/v1/plans/{$plan->id}/deactivate");

        $response->assertStatus(200)->assertJsonPath('data.is_active', false);

        $publicResponse = $this->getJson('/api/v1/plans');
        $publicResponse->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_GIVEN_a_non_super_admin_admin_user_WHEN_attempting_plan_crud_THEN_it_is_rejected_with_403(): void
    {
        $admin = AdminUserModel::factory()->create();
        Sanctum::actingAs($admin, ['*'], 'admin');

        $response = $this->getJson('/api/admin/v1/plans');

        $response->assertStatus(403);
    }

    public function test_GIVEN_a_non_super_admin_forging_a_super_admin_permissions_claim_WHEN_attempting_plan_crud_THEN_it_is_still_rejected_with_403(): void
    {
        $admin = AdminUserModel::factory()->create();
        Sanctum::actingAs($admin, ['*'], 'admin');

        $response = $this->getJson('/api/admin/v1/plans?permissions[]=plans.manage', [
            'X-Permissions' => 'plans.manage',
        ]);

        $response->assertStatus(403);
    }

    public function test_GIVEN_a_fan_token_WHEN_attempting_plan_crud_THEN_it_is_rejected(): void
    {
        $fan = UserModel::factory()->create();
        $token = $fan->createToken('mobile')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/admin/v1/plans');

        $response->assertStatus(401);
    }

    public function test_GIVEN_an_unauthenticated_request_WHEN_attempting_plan_crud_THEN_it_is_rejected(): void
    {
        $response = $this->getJson('/api/admin/v1/plans');

        $response->assertStatus(401);
    }
}
