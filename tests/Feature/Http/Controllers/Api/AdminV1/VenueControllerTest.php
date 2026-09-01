<?php

namespace Tests\Feature\Http\Controllers\Api\AdminV1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use QOR\App\Http\Controllers\Api\AdminV1\VenueController;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\PromoterModel;
use QOR\App\Infrastructure\Persistence\Eloquent\VenueModel;
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

        Route::middleware(['api', 'auth:admin', 'guard.admin'])
            ->prefix('api/admin/v1')
            ->patch('venues/me', [VenueController::class, 'update']);

        Route::middleware(['api', 'auth:admin', 'guard.admin'])
            ->prefix('api/admin/v1')
            ->get('venues/me', [VenueController::class, 'show']);
    }

    private function actingAsVenueAdmin(): VenueModel
    {
        $admin = AdminUserModel::factory()->create();
        $venue = VenueModel::factory()->approved()->create(['venue_admin_user_id' => $admin->id]);

        Sanctum::actingAs($admin, ['*'], 'admin');

        return $venue;
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

    public function test_GIVEN_an_authenticated_venue_admin_WHEN_editing_the_own_profile_THEN_the_provided_fields_are_persisted(): void
    {
        $venue = $this->actingAsVenueAdmin();

        $response = $this->patchJson('/api/admin/v1/venues/me', [
            'name' => 'Casa de Show Renovada',
            'contact_phone' => '27988887777',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $venue->id)
            ->assertJsonPath('data.name', 'Casa de Show Renovada')
            ->assertJsonPath('data.contact_phone', '27988887777')
            ->assertJsonPath('data.address', $venue->address);

        $this->assertDatabaseHas('venues', [
            'id' => $venue->id,
            'name' => 'Casa de Show Renovada',
            'contact_phone' => '27988887777',
            'address' => $venue->address,
        ]);
    }

    public function test_GIVEN_an_image_upload_WHEN_editing_the_own_profile_THEN_it_persists_the_stored_url(): void
    {
        Storage::fake('s3');
        $this->actingAsVenueAdmin();
        $file = UploadedFile::fake()->image('logo.jpg', 800, 600);

        $response = $this->patch('/api/admin/v1/venues/me', ['image' => $file]);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('data.image_url'));
    }

    public function test_GIVEN_no_authenticated_admin_WHEN_editing_a_venue_profile_THEN_it_is_rejected(): void
    {
        $response = $this->patchJson('/api/admin/v1/venues/me', ['name' => 'Nome Qualquer']);

        $response->assertStatus(401);
    }

    public function test_GIVEN_an_authenticated_venue_admin_WHEN_getting_their_own_venue_THEN_it_returns_the_venue_including_its_address(): void
    {
        $venue = $this->actingAsVenueAdmin();

        $response = $this->getJson('/api/admin/v1/venues/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $venue->id)
            ->assertJsonPath('data.name', $venue->name)
            ->assertJsonPath('data.address', $venue->address);
    }

    public function test_GIVEN_an_authenticated_promoter_WHEN_getting_venues_me_THEN_it_returns_404(): void
    {
        $admin = AdminUserModel::factory()->create();
        PromoterModel::factory()->approved()->create(['user_id' => $admin->id]);
        Sanctum::actingAs($admin, ['*'], 'admin');

        $response = $this->getJson('/api/admin/v1/venues/me');

        $response->assertStatus(404)->assertJsonPath('message', 'Venue não encontrada.');
    }

    public function test_GIVEN_no_authenticated_admin_WHEN_getting_venues_me_THEN_it_is_rejected(): void
    {
        $response = $this->getJson('/api/admin/v1/venues/me');

        $response->assertStatus(401);
    }
}
