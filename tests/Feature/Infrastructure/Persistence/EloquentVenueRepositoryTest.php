<?php

namespace Tests\Feature\Infrastructure\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Infrastructure\Persistence\Eloquent\VenueModel;
use QOR\App\Infrastructure\Persistence\EloquentVenueRepository;
use Tests\TestCase;

class EloquentVenueRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_a_persisted_venue_WHEN_finding_by_id_THEN_it_maps_to_the_domain_entity(): void
    {
        $model = VenueModel::factory()->create(['name' => 'Clube da Esquina']);

        $repository = new EloquentVenueRepository();

        $found = $repository->findById($model->id);

        $this->assertNotNull($found);
        $this->assertSame('Clube da Esquina', $found->name);
        $this->assertSame(ApprovalStatus::PendingApproval, $found->approvalStatus);
    }

    public function test_GIVEN_a_domain_venue_WHEN_saving_an_update_THEN_changes_persist(): void
    {
        $model = VenueModel::factory()->create();
        $repository = new EloquentVenueRepository();
        $venue = $repository->findById($model->id);

        $updated = $repository->save(new \QOR\App\Domain\Venue\Venue(
            id: $venue->id,
            venueAdminUserId: $venue->venueAdminUserId,
            name: 'Novo Nome',
            description: $venue->description,
            address: $venue->address,
            city: $venue->city,
            contactPhone: $venue->contactPhone,
            contactEmail: $venue->contactEmail,
            approvalStatus: ApprovalStatus::Approved,
        ));

        $this->assertSame('Novo Nome', $updated->name);
        $this->assertDatabaseHas('venues', ['id' => $model->id, 'name' => 'Novo Nome', 'approval_status' => 'approved']);
    }
}
