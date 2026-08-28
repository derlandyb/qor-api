<?php

namespace Tests\Feature\Infrastructure\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use QOR\App\Domain\User\User;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;
use QOR\App\Infrastructure\Persistence\EloquentUserRepository;
use Tests\TestCase;

class EloquentUserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_a_persisted_user_WHEN_finding_by_email_THEN_it_maps_to_the_domain_entity(): void
    {
        UserModel::factory()->create(['email' => 'ana@example.com', 'name' => 'Ana Silva']);

        $repository = new EloquentUserRepository();

        $found = $repository->findByEmail('ana@example.com');

        $this->assertNotNull($found);
        $this->assertSame('Ana Silva', $found->name);
    }

    public function test_GIVEN_a_soft_deleted_user_WHEN_deleting_THEN_it_is_no_longer_findable(): void
    {
        $model = UserModel::factory()->create();
        $repository = new EloquentUserRepository();

        $repository->delete($model->id);

        $this->assertNull($repository->findById($model->id));
        $this->assertSoftDeleted('users', ['id' => $model->id]);
    }

    public function test_GIVEN_a_new_domain_user_WHEN_saving_THEN_it_is_persisted_and_assigned_an_id(): void
    {
        $user = new User(
            id: null,
            name: 'Bia Costa',
            email: 'bia@example.com',
            birthdate: new \DateTimeImmutable('1998-04-10'),
            passwordHash: 'hashed-password',
            emailVerifiedAt: new \DateTimeImmutable('2026-01-01 10:00:00'),
        );

        $repository = new EloquentUserRepository();

        $saved = $repository->save($user);

        $this->assertNotNull($saved->id);
        $this->assertTrue($saved->isVerified());
        $this->assertDatabaseHas('users', ['id' => $saved->id, 'email' => 'bia@example.com']);
    }

    public function test_GIVEN_a_persisted_user_WHEN_saving_with_an_id_THEN_the_existing_row_is_updated(): void
    {
        $model = UserModel::factory()->create(['name' => 'Old Name']);
        $repository = new EloquentUserRepository();
        $existing = $repository->findById($model->id);

        $updated = new User(
            id: $existing->id,
            name: 'New Name',
            email: $existing->email,
            birthdate: $existing->birthdate,
            passwordHash: $existing->passwordHash,
        );

        $saved = $repository->save($updated);

        $this->assertSame($model->id, $saved->id);
        $this->assertSame('New Name', $saved->name);
        $this->assertDatabaseHas('users', ['id' => $model->id, 'name' => 'New Name']);
    }
}
