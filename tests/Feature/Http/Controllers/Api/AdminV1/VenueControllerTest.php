<?php

namespace Tests\Feature\Http\Controllers\Api\AdminV1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use QOR\App\Http\Controllers\Api\AdminV1\VenueController;
use Tests\TestCase;

class VenueControllerTest extends TestCase
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
            ->post('venues', [VenueController::class, 'register']);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Casa de Show Central',
            'description' => 'Um espaço para shows ao vivo.',
            'address' => 'Rua das Flores, 100',
            'city' => 'vitoria',
            'contact_phone' => '27999998888',
            'contact_email' => 'contato@casadeshow.com',
            'registration_email' => 'admin@casadeshow.com',
            'password' => 'Senha123',
            'terms_accepted' => true,
        ], $overrides);
    }

    public function test_GIVEN_valid_fields_WHEN_registering_a_venue_THEN_it_creates_a_pending_venue(): void
    {
        $response = $this->postJson('/api/admin/v1/venues', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Casa de Show Central')
            ->assertJsonPath('data.city', 'vitoria')
            ->assertJsonPath('data.approval_status', 'pending_approval')
            ->assertJsonMissing(['password'])
            ->assertJsonMissing(['venueAdminUserId'])
            ->assertJsonMissing(['userId']);

        $this->assertDatabaseHas('venues', ['name' => 'Casa de Show Central']);
        $this->assertDatabaseHas('admin_users', ['email' => 'admin@casadeshow.com']);
        $this->assertDatabaseHas('consent_records', ['consentable_type' => 'admin_user', 'consent_type' => 'terms']);
    }

    public function test_GIVEN_a_duplicate_registration_email_WHEN_registering_a_venue_THEN_it_returns_422(): void
    {
        $this->postJson('/api/admin/v1/venues', $this->validPayload())->assertStatus(201);

        $response = $this->postJson('/api/admin/v1/venues', $this->validPayload([
            'name' => 'Outra Casa de Show',
            'contact_email' => 'outro@casadeshow.com',
        ]));

        $response->assertStatus(422)->assertJsonPath('message', 'Este e-mail já está cadastrado');
    }

    public function test_GIVEN_terms_not_accepted_WHEN_registering_a_venue_THEN_it_returns_422(): void
    {
        $payload = $this->validPayload();
        unset($payload['terms_accepted']);

        $response = $this->postJson('/api/admin/v1/venues', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors(['terms_accepted']);
    }

    public function test_GIVEN_an_invalid_city_WHEN_registering_a_venue_THEN_it_returns_422(): void
    {
        $response = $this->postJson('/api/admin/v1/venues', $this->validPayload(['city' => 'nao_existe']));

        $response->assertStatus(422)->assertJsonValidationErrors(['city']);
    }

    public function test_GIVEN_a_missing_name_WHEN_registering_a_venue_THEN_it_returns_422(): void
    {
        $payload = $this->validPayload();
        unset($payload['name']);

        $response = $this->postJson('/api/admin/v1/venues', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors(['name']);
    }
}
