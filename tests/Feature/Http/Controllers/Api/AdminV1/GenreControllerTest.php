<?php

namespace Tests\Feature\Http\Controllers\Api\AdminV1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\GenreModel;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\TestCase;

class GenreControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): AdminUserModel
    {
        $admin = AdminUserModel::factory()->superAdmin()->create();

        Sanctum::actingAs($admin, ['*'], 'admin');

        return $admin;
    }

    private function actingAsNonSuperAdmin(): AdminUserModel
    {
        $admin = AdminUserModel::factory()->create();

        Sanctum::actingAs($admin, ['*'], 'admin');

        return $admin;
    }

    public function test_GIVEN_valid_genre_data_WHEN_a_super_admin_creates_a_genre_THEN_it_is_created(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/admin/v1/genres', ['name' => 'Rock']);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Rock')
            ->assertJsonPath('data.slug', 'rock')
            ->assertJsonPath('data.is_active', true);
    }

    public function test_GIVEN_active_and_inactive_genres_WHEN_a_non_super_admin_lists_genres_THEN_the_response_includes_both(): void
    {
        $this->actingAsNonSuperAdmin();
        GenreModel::factory()->create(['name' => 'Rock', 'is_active' => true]);
        GenreModel::factory()->create(['name' => 'Forró', 'is_active' => false]);

        $response = $this->getJson('/api/admin/v1/genres');

        $response->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_GIVEN_a_missing_name_WHEN_creating_a_genre_THEN_it_returns_a_field_specific_error(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/admin/v1/genres', []);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors' => ['name']]);
    }

    public function test_GIVEN_a_duplicate_name_WHEN_creating_a_genre_THEN_it_returns_a_field_specific_error(): void
    {
        $this->actingAsSuperAdmin();
        GenreModel::factory()->create(['name' => 'Rock']);

        $response = $this->postJson('/api/admin/v1/genres', ['name' => 'Rock']);

        $response->assertStatus(422)->assertJsonStructure(['message', 'errors' => ['name']]);
    }

    public function test_GIVEN_an_existing_genre_WHEN_a_super_admin_updates_its_name_THEN_the_change_applies(): void
    {
        $this->actingAsSuperAdmin();
        $genre = GenreModel::factory()->create(['name' => 'Rock']);

        $response = $this->patchJson("/api/admin/v1/genres/{$genre->id}", ['name' => 'Rock Nacional']);

        $response->assertStatus(200)->assertJsonPath('data.name', 'Rock Nacional');
        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => 'Rock Nacional']);
    }

    public function test_GIVEN_an_active_genre_WHEN_a_super_admin_deactivates_it_THEN_it_becomes_inactive(): void
    {
        $this->actingAsSuperAdmin();
        $genre = GenreModel::factory()->create(['is_active' => true]);

        $response = $this->postJson("/api/admin/v1/genres/{$genre->id}/deactivate");

        $response->assertStatus(200)->assertJsonPath('data.is_active', false);
    }

    public function test_GIVEN_an_inactive_genre_WHEN_a_super_admin_activates_it_THEN_it_becomes_active(): void
    {
        $this->actingAsSuperAdmin();
        $genre = GenreModel::factory()->create(['is_active' => false]);

        $response = $this->postJson("/api/admin/v1/genres/{$genre->id}/activate");

        $response->assertStatus(200)->assertJsonPath('data.is_active', true);
    }

    public function test_GIVEN_a_non_super_admin_admin_user_WHEN_listing_genres_THEN_it_succeeds(): void
    {
        $this->actingAsNonSuperAdmin();

        $response = $this->getJson('/api/admin/v1/genres');

        $response->assertStatus(200);
    }

    public function test_GIVEN_a_non_super_admin_admin_user_WHEN_attempting_genre_mutations_THEN_it_is_rejected_with_403(): void
    {
        $this->actingAsNonSuperAdmin();

        $response = $this->postJson('/api/admin/v1/genres', ['name' => 'Rock']);

        $response->assertStatus(403);
    }

    public function test_GIVEN_a_non_super_admin_forging_a_super_admin_permissions_claim_WHEN_attempting_genre_mutations_THEN_it_is_still_rejected_with_403(): void
    {
        $this->actingAsNonSuperAdmin();

        $response = $this->postJson('/api/admin/v1/genres?permissions[]=genres.manage', [
            'name' => 'Rock',
        ], ['X-Permissions' => 'genres.manage']);

        $response->assertStatus(403);
    }

    public function test_GIVEN_a_fan_token_WHEN_attempting_genre_crud_THEN_it_is_rejected(): void
    {
        $fan = UserModel::factory()->create();
        $token = $fan->createToken('mobile')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/admin/v1/genres');

        $response->assertStatus(401);
    }

    public function test_GIVEN_an_unauthenticated_request_WHEN_attempting_genre_crud_THEN_it_is_rejected(): void
    {
        $response = $this->getJson('/api/admin/v1/genres');

        $response->assertStatus(401);
    }
}
