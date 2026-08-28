<?php

namespace Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use QOR\App\Http\Middleware\EnsureSuperAdmin;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureSuperAdminTest extends TestCase
{
    public function test_GIVEN_a_non_super_admin_WHEN_handling_THEN_it_aborts_with_403(): void
    {
        $admin = new AdminUserModel();
        $admin->is_super_admin = false;

        Auth::shouldReceive('guard')->with('admin')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($admin);

        $middleware = new EnsureSuperAdmin();

        try {
            $middleware->handle(Request::create('/'), fn ($request) => $request);
            $this->fail('Expected an HttpException to be thrown.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_GIVEN_a_super_admin_WHEN_handling_THEN_the_request_proceeds(): void
    {
        $admin = new AdminUserModel();
        $admin->is_super_admin = true;

        Auth::shouldReceive('guard')->with('admin')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($admin);

        $middleware = new EnsureSuperAdmin();
        $request = Request::create('/');
        $response = new Response();

        $result = $middleware->handle($request, fn ($req) => $response);

        $this->assertSame($response, $result);
    }
}
