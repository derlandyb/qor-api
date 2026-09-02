<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\TestCase;

class DebugControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_a_code_was_just_issued_WHEN_fetching_the_debug_endpoint_THEN_it_returns_that_code(): void
    {
        UserModel::factory()->unverified()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/v1/auth/email/verification-notification', ['email' => 'ana@example.com']);

        $response = $this->getJson('/api/v1/_debug/otp?purpose=email_verification&email=ana@example.com');

        $response->assertStatus(200)->assertJsonStructure(['data' => ['code']]);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $response->json('data.code'));
    }

    public function test_GIVEN_no_code_was_issued_WHEN_fetching_the_debug_endpoint_THEN_it_returns_404(): void
    {
        $response = $this->getJson('/api/v1/_debug/otp?purpose=email_verification&email=ninguem@example.com');

        $response->assertStatus(404);
    }
}
