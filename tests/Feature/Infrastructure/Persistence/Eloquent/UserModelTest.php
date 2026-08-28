<?php

namespace Tests\Feature\Infrastructure\Persistence\Eloquent;

use Illuminate\Foundation\Testing\RefreshDatabase;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_a_user_with_a_password_hash_WHEN_getting_the_auth_password_THEN_it_returns_the_hash(): void
    {
        $user = UserModel::factory()->create();

        $this->assertSame($user->password_hash, $user->getAuthPassword());
    }
}
