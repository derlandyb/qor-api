<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use QOR\App\Models\AdminUser;
use QOR\App\Models\User;
use Tests\TestCase;

class GuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:fan', 'guard.fan'])
            ->get('/__test/fan-only', fn () => response()->json(['ok' => true]));

        Route::middleware(['auth:admin', 'guard.admin'])
            ->get('/__test/admin-only', fn () => response()->json(['ok' => true]));
    }

    public function test_GIVEN_a_fan_bearer_token_WHEN_hitting_an_admin_guarded_route_THEN_it_is_rejected(): void
    {
        $fan = User::factory()->create();
        $token = $fan->createToken('mobile')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/__test/admin-only');

        $response->assertStatus(401);
    }

    public function test_GIVEN_an_admin_bearer_token_WHEN_hitting_a_fan_guarded_route_THEN_it_is_rejected(): void
    {
        $admin = AdminUser::factory()->create();
        $token = $admin->createToken('admin-panel')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/__test/fan-only');

        $response->assertStatus(401);
    }

    public function test_GIVEN_a_fan_bearer_token_WHEN_hitting_a_fan_guarded_route_THEN_it_is_authorized(): void
    {
        $fan = User::factory()->create();
        $token = $fan->createToken('mobile')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/__test/fan-only');

        $response->assertStatus(200);
    }

    public function test_GIVEN_an_admin_bearer_token_WHEN_hitting_an_admin_guarded_route_THEN_it_is_authorized(): void
    {
        $admin = AdminUser::factory()->create();
        $token = $admin->createToken('admin-panel')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/__test/admin-only');

        $response->assertStatus(200);
    }
}
