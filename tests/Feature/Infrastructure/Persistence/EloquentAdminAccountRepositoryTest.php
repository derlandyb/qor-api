<?php

namespace Tests\Feature\Infrastructure\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use QOR\App\Domain\Admin\AdminAccount;
use QOR\App\Infrastructure\Persistence\Eloquent\AdminUserModel;
use QOR\App\Infrastructure\Persistence\EloquentAdminAccountRepository;
use Tests\TestCase;

class EloquentAdminAccountRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_no_existing_account_WHEN_saving_a_new_account_THEN_it_is_persisted(): void
    {
        $repository = new EloquentAdminAccountRepository();

        $saved = $repository->save(new AdminAccount(
            id: null,
            name: 'Casa de Show Vitória',
            email: 'contato@casadeshow.com',
            passwordHash: 'hashed-password',
        ));

        $this->assertNotNull($saved->id);
        $this->assertDatabaseHas('admin_users', [
            'id' => $saved->id,
            'email' => 'contato@casadeshow.com',
            'is_super_admin' => false,
        ]);
    }

    public function test_GIVEN_a_persisted_account_WHEN_finding_by_email_THEN_it_is_returned(): void
    {
        $model = AdminUserModel::factory()->create(['email' => 'venue@example.com']);
        $repository = new EloquentAdminAccountRepository();

        $found = $repository->findByEmail('venue@example.com');

        $this->assertNotNull($found);
        $this->assertSame($model->id, $found->id);
    }

    public function test_GIVEN_no_account_with_email_WHEN_finding_by_email_THEN_null_is_returned(): void
    {
        $repository = new EloquentAdminAccountRepository();

        $this->assertNull($repository->findByEmail('missing@example.com'));
    }
}
