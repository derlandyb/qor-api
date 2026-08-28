<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;
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
        $fan = UserModel::factory()->create();
        $token = $fan->createToken('mobile')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/__test/admin-only');

        $response->assertStatus(401);
    }

    public function test_GIVEN_an_admin_bearer_token_WHEN_hitting_a_fan_guarded_route_THEN_it_is_rejected(): void
    {
        $admin = AdminUserModel::factory()->create();
        $token = $admin->createToken('admin-panel')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/__test/fan-only');

        $response->assertStatus(401);
    }

    public function test_GIVEN_a_fan_bearer_token_WHEN_hitting_a_fan_guarded_route_THEN_it_is_authorized(): void
    {
        $fan = UserModel::factory()->create();
        $token = $fan->createToken('mobile')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/__test/fan-only');

        $response->assertStatus(200);
    }

    public function test_GIVEN_an_admin_bearer_token_WHEN_hitting_an_admin_guarded_route_THEN_it_is_authorized(): void
    {
        $admin = AdminUserModel::factory()->create();
        $token = $admin->createToken('admin-panel')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/__test/admin-only');

        $response->assertStatus(200);
    }

    public function test_GIVEN_no_token_WHEN_hitting_a_fan_guarded_route_THEN_it_is_rejected(): void
    {
        $response = $this->getJson('/__test/fan-only');

        $response->assertStatus(401);
    }

    public function test_GIVEN_no_token_WHEN_hitting_an_admin_guarded_route_THEN_it_is_rejected(): void
    {
        $response = $this->getJson('/__test/admin-only');

        $response->assertStatus(401);
    }
}
