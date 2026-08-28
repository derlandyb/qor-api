<?php

namespace Tests\Feature\Http\Controllers\Api\AdminV1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use QOR\App\Http\Controllers\Api\AdminV1\PromoterController;
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
}
