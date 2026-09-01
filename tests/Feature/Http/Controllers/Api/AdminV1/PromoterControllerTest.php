<?php

namespace Tests\Feature\Http\Controllers\Api\AdminV1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use QOR\App\Http\Controllers\Api\AdminV1\PromoterController;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\PromoterModel;
use QOR\App\Infrastructure\Persistence\Eloquent\VenueModel;
use Tests\TestCase;

class PromoterControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Route registered here only for this test suite: the orchestrator
        // wires routes/api_admin_v1.php centrally once every controller
        // task lands, to avoid merge conflicts between parallel agents.
        Route::middleware('api')
            ->prefix('api/admin/v1')
            ->post('promoters', [PromoterController::class, 'register']);

        Route::middleware(['api', 'auth:admin', 'guard.admin'])
            ->prefix('api/admin/v1')
            ->patch('promoters/me', [PromoterController::class, 'update']);

        Route::middleware(['api', 'auth:admin', 'guard.admin'])
            ->prefix('api/admin/v1')
            ->get('promoters/me', [PromoterController::class, 'show']);
    }

    private function actingAsPromoter(): PromoterModel
    {
        $admin = AdminUserModel::factory()->create();
        $promoter = PromoterModel::factory()->approved()->create(['user_id' => $admin->id]);

        Sanctum::actingAs($admin, ['*'], 'admin');

        return $promoter;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Produtora Vitória Eventos',
            'contact_phone' => '27999997777',
            'contact_email' => 'contato@produtora.com',
            'instagram' => '@produtoravitoria',
            'tiktok' => '@produtoravitoria',
            'registration_email' => 'admin@produtora.com',
            'password' => 'Senha123',
            'terms_accepted' => true,
        ], $overrides);
    }

    public function test_GIVEN_valid_fields_WHEN_registering_a_promoter_THEN_it_creates_a_pending_promoter(): void
    {
        $response = $this->postJson('/api/admin/v1/promoters', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Produtora Vitória Eventos')
            ->assertJsonPath('data.instagram', '@produtoravitoria')
            ->assertJsonPath('data.approval_status', 'pending_approval')
            ->assertJsonMissing(['password'])
            ->assertJsonMissing(['userId']);

        $this->assertDatabaseHas('promoters', ['name' => 'Produtora Vitória Eventos']);
        $this->assertDatabaseHas('admin_users', ['email' => 'admin@produtora.com']);
        $this->assertDatabaseHas('consent_records', ['consentable_type' => 'admin_user', 'consent_type' => 'terms']);
    }

    public function test_GIVEN_a_duplicate_registration_email_WHEN_registering_a_promoter_THEN_it_returns_422(): void
    {
        $this->postJson('/api/admin/v1/promoters', $this->validPayload())->assertStatus(201);

        $response = $this->postJson('/api/admin/v1/promoters', $this->validPayload([
            'name' => 'Outra Produtora',
            'contact_email' => 'outra@produtora.com',
        ]));

        $response->assertStatus(422)->assertJsonPath('message', 'Este e-mail já está cadastrado');
    }

    public function test_GIVEN_terms_not_accepted_WHEN_registering_a_promoter_THEN_it_returns_422(): void
    {
        $payload = $this->validPayload();
        unset($payload['terms_accepted']);

        $response = $this->postJson('/api/admin/v1/promoters', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors(['terms_accepted']);
    }

    public function test_GIVEN_optional_fields_omitted_WHEN_registering_a_promoter_THEN_it_creates_a_promoter_without_social_handles(): void
    {
        $payload = $this->validPayload();
        unset($payload['instagram'], $payload['tiktok']);

        $response = $this->postJson('/api/admin/v1/promoters', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.instagram', null)
            ->assertJsonPath('data.tiktok', null);
    }

    public function test_GIVEN_a_missing_name_WHEN_registering_a_promoter_THEN_it_returns_422(): void
    {
        $payload = $this->validPayload();
        unset($payload['name']);

        $response = $this->postJson('/api/admin/v1/promoters', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_GIVEN_an_authenticated_promoter_WHEN_editing_the_own_profile_THEN_the_provided_fields_are_persisted(): void
    {
        $promoter = $this->actingAsPromoter();

        $response = $this->patchJson('/api/admin/v1/promoters/me', [
            'name' => 'Produtora Renovada',
            'instagram' => '@novoinsta',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $promoter->id)
            ->assertJsonPath('data.name', 'Produtora Renovada')
            ->assertJsonPath('data.instagram', '@novoinsta')
            ->assertJsonPath('data.contact_phone', $promoter->contact_phone);

        $this->assertDatabaseHas('promoters', [
            'id' => $promoter->id,
            'name' => 'Produtora Renovada',
            'instagram' => '@novoinsta',
        ]);
    }

    public function test_GIVEN_no_authenticated_admin_WHEN_editing_a_promoter_profile_THEN_it_is_rejected(): void
    {
        $response = $this->patchJson('/api/admin/v1/promoters/me', ['name' => 'Nome Qualquer']);

        $response->assertStatus(401);
    }

    public function test_GIVEN_an_authenticated_promoter_WHEN_getting_their_own_promoter_THEN_it_returns_the_promoter(): void
    {
        $promoter = $this->actingAsPromoter();

        $response = $this->getJson('/api/admin/v1/promoters/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $promoter->id)
            ->assertJsonPath('data.name', $promoter->name)
            ->assertJsonPath('data.instagram', $promoter->instagram);
    }

    public function test_GIVEN_an_authenticated_venue_admin_WHEN_getting_promoters_me_THEN_it_returns_404(): void
    {
        $admin = AdminUserModel::factory()->create();
        VenueModel::factory()->approved()->create(['venue_admin_user_id' => $admin->id]);
        Sanctum::actingAs($admin, ['*'], 'admin');

        $response = $this->getJson('/api/admin/v1/promoters/me');

        $response->assertStatus(404)->assertJsonPath('message', 'Promoter não encontrado.');
    }

    public function test_GIVEN_no_authenticated_admin_WHEN_getting_promoters_me_THEN_it_is_rejected(): void
    {
        $response = $this->getJson('/api/admin/v1/promoters/me');

        $response->assertStatus(401);
    }
}
