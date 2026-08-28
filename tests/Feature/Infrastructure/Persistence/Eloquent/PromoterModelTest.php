<?php

namespace Tests\Feature\Infrastructure\Persistence\Eloquent;

use Illuminate\Foundation\Testing\RefreshDatabase;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\Eloquent\PromoterModel;
use Tests\TestCase;

class PromoterModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_a_promoter_WHEN_loading_the_user_relation_THEN_it_resolves_the_owning_admin(): void
    {
        $admin = AdminUserModel::factory()->create();
        $promoter = PromoterModel::factory()->create(['user_id' => $admin->id]);

        $this->assertSame($admin->id, $promoter->user->id);
    }
}
