<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use QOR\App\Domain\User\Enum\ConsentType;
use QOR\App\Infrastructure\Persistence\Eloquent\ConsentRecordModel;
use QOR\App\Infrastructure\Persistence\Eloquent\UserAddressModel;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(UserModel $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('mobile')->plainTextToken];
    }

    public function test_GIVEN_no_token_WHEN_viewing_the_profile_THEN_it_is_rejected(): void
    {
        $this->getJson('/api/v1/profile')->assertStatus(401);
    }

    public function test_GIVEN_an_authenticated_fan_WHEN_viewing_the_profile_THEN_it_returns_the_profile_fields(): void
    {
        $user = UserModel::factory()->create(['name' => 'Ana Silva']);

        $response = $this->withHeaders($this->authHeader($user))->getJson('/api/v1/profile');

        $response->assertStatus(200)->assertJsonPath('data.name', 'Ana Silva');
    }

    public function test_GIVEN_a_name_and_phone_change_WHEN_updating_the_profile_THEN_they_persist_immediately(): void
    {
        $user = UserModel::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->patchJson('/api/v1/profile', ['name' => 'Novo Nome', 'phone' => '27988887777']);

        $response->assertStatus(200)->assertJsonPath('data.name', 'Novo Nome');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Novo Nome', 'phone' => '27988887777']);
    }

    public function test_GIVEN_a_new_email_WHEN_updating_the_profile_THEN_the_old_email_stays_active_until_verified(): void
    {
        Notification::fake();
        $user = UserModel::factory()->create(['email' => 'antigo@example.com']);

        $response = $this->withHeaders($this->authHeader($user))
            ->patchJson('/api/v1/profile', ['email' => 'novo@example.com']);

        $response->assertStatus(200)->assertJsonPath('data.email', 'antigo@example.com')
            ->assertJsonPath('data.pending_email', 'novo@example.com');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'antigo@example.com', 'pending_email' => 'novo@example.com']);
    }

    public function test_GIVEN_an_image_upload_WHEN_updating_the_profile_picture_THEN_it_persists_the_stored_url(): void
    {
        Storage::fake('s3');
        $user = UserModel::factory()->create();
        $file = UploadedFile::fake()->image('avatar.jpg', 500, 500);

        $response = $this->withHeaders($this->authHeader($user))
            ->post('/api/v1/profile/picture', ['picture' => $file]);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('data.profile_picture_url'));
    }

    public function test_GIVEN_an_authenticated_fan_WHEN_accessing_their_data_THEN_it_returns_a_full_summary(): void
    {
        $user = UserModel::factory()->create();
        ConsentRecordModel::factory()->create(['consentable_id' => $user->id]);

        $response = $this->withHeaders($this->authHeader($user))->getJson('/api/v1/profile/data-rights/access');

        $response->assertStatus(200)->assertJsonPath('data.email', $user->email)
            ->assertJsonCount(1, 'data.consents');
    }

    public function test_GIVEN_an_authenticated_fan_WHEN_exporting_their_data_THEN_it_returns_a_portable_payload(): void
    {
        $user = UserModel::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))->getJson('/api/v1/profile/data-rights/export');

        $response->assertStatus(200)->assertJsonPath('data.email', $user->email);
    }

    public function test_GIVEN_an_authenticated_fan_WHEN_deleting_their_account_THEN_it_is_soft_deleted_and_the_token_revoked(): void
    {
        $user = UserModel::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/profile/data-rights/delete');

        $response->assertStatus(200);
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_GIVEN_an_active_location_consent_WHEN_revoking_it_THEN_it_stops_immediately(): void
    {
        $user = UserModel::factory()->create();
        ConsentRecordModel::factory()->create([
            'consentable_id' => $user->id,
            'consent_type' => ConsentType::Location->value,
        ]);

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/profile/data-rights/revoke', ['consent_type' => 'location']);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('consent_records', [
            'consentable_id' => $user->id,
            'consent_type' => ConsentType::Location->value,
            'revoked_at' => null,
        ]);
    }

    public function test_GIVEN_no_address_yet_WHEN_viewing_the_address_THEN_it_returns_null(): void
    {
        $user = UserModel::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))->getJson('/api/v1/profile/address');

        $response->assertStatus(200)->assertJsonPath('data', null);
    }

    public function test_GIVEN_a_manual_address_WHEN_setting_it_THEN_it_persists(): void
    {
        $user = UserModel::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->patchJson('/api/v1/profile/address', [
                'city' => 'vitoria',
                'state' => 'ES',
                'street' => 'Rua das Flores',
                'number' => '123',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.city', 'vitoria')
            ->assertJsonPath('data.source', 'manual');
        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $user->id,
            'city' => 'vitoria',
            'street' => 'Rua das Flores',
        ]);
    }

    public function test_GIVEN_an_existing_address_WHEN_updating_it_again_THEN_it_replaces_the_previous_one(): void
    {
        $user = UserModel::factory()->create();
        UserAddressModel::factory()->create(['user_id' => $user->id, 'city' => 'serra']);

        $response = $this->withHeaders($this->authHeader($user))
            ->patchJson('/api/v1/profile/address', ['city' => 'vila_velha', 'state' => 'ES']);

        $response->assertStatus(200)->assertJsonPath('data.city', 'vila_velha');
        $this->assertSame(1, UserAddressModel::where('user_id', $user->id)->count());
    }

    public function test_GIVEN_no_preferences_yet_WHEN_viewing_preferences_THEN_it_returns_empty_defaults(): void
    {
        $user = UserModel::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))->getJson('/api/v1/profile/preferences');

        $response->assertStatus(200)
            ->assertJsonPath('data.genre_ids', [])
            ->assertJsonPath('data.radius_km', null);
    }

    public function test_GIVEN_favorite_genres_and_a_radius_WHEN_updating_preferences_THEN_they_persist(): void
    {
        $user = UserModel::factory()->create();
        UserAddressModel::factory()->create(['user_id' => $user->id]);
        $genreId = DB::table('genres')->insertGetId([
            'name' => 'Rock', 'slug' => 'rock-'.$user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->withHeaders($this->authHeader($user))
            ->patchJson('/api/v1/profile/preferences', ['genre_ids' => [$genreId], 'radius_km' => 15]);

        $response->assertStatus(200)
            ->assertJsonPath('data.genre_ids', [$genreId])
            ->assertJsonPath('data.radius_km', 15);
    }

    public function test_GIVEN_an_invalid_genre_id_WHEN_updating_preferences_THEN_it_is_rejected(): void
    {
        $user = UserModel::factory()->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->patchJson('/api/v1/profile/preferences', ['genre_ids' => [999999]]);

        $response->assertStatus(422);
    }
}
