<?php

namespace Tests\Feature\Infrastructure\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Domain\Promoter\Promoter;
use QOR\App\Infrastructure\Persistence\Eloquent\PromoterModel;
use QOR\App\Infrastructure\Persistence\EloquentPromoterRepository;
use Tests\TestCase;

class EloquentPromoterRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_a_persisted_promoter_WHEN_finding_by_id_THEN_it_maps_to_the_domain_entity(): void
    {
        $model = PromoterModel::factory()->create(['name' => 'Produções Maré']);

        $repository = new EloquentPromoterRepository();

        $found = $repository->findById($model->id);

        $this->assertNotNull($found);
        $this->assertSame('Produções Maré', $found->name);
    }

    public function test_GIVEN_a_domain_promoter_WHEN_saving_an_update_THEN_changes_persist(): void
    {
        $model = PromoterModel::factory()->create();
        $repository = new EloquentPromoterRepository();
        $promoter = $repository->findById($model->id);

        $updated = $repository->save(new Promoter(
            id: $promoter->id,
            userId: $promoter->userId,
            name: 'Novo Nome',
            contactPhone: $promoter->contactPhone,
            contactEmail: $promoter->contactEmail,
            approvalStatus: ApprovalStatus::Approved,
            instagram: $promoter->instagram,
            tiktok: $promoter->tiktok,
        ));

        $this->assertSame('Novo Nome', $updated->name);
        $this->assertDatabaseHas('promoters', ['id' => $model->id, 'name' => 'Novo Nome', 'approval_status' => 'approved']);
    }
}
