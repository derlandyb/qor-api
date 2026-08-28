<?php

namespace Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use QOR\App\Http\Middleware\EnsureAdminIdentity;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureAdminIdentityTest extends TestCase
{
    public function test_GIVEN_no_authenticated_admin_WHEN_handling_THEN_it_aborts_with_401(): void
    {
        Auth::shouldReceive('guard')->with('admin')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn(null);

        $middleware = new EnsureAdminIdentity();

        try {
            $middleware->handle(Request::create('/'), fn ($request) => $request);
            $this->fail('Expected an HttpException to be thrown.');
        } catch (HttpException $e) {
            $this->assertSame(401, $e->getStatusCode());
        }
    }
}
