<?php

namespace Tests\Feature\Infrastructure\Persistence\Eloquent;

use Illuminate\Foundation\Testing\RefreshDatabase;
use QOR\App\Domain\User\Enum\ConsentableType;
use QOR\App\Domain\User\Enum\ConsentType;
use QOR\App\Infrastructure\Persistence\Eloquent\ConsentRecordModel;
use Tests\TestCase;

class ConsentRecordModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_a_factory_created_record_WHEN_persisted_THEN_enum_casts_resolve_correctly(): void
    {
        $record = ConsentRecordModel::factory()->create([
            'consentable_type' => ConsentableType::AdminUser->value,
            'consent_type' => ConsentType::Location->value,
        ]);

        $fresh = ConsentRecordModel::find($record->id);

        $this->assertSame(ConsentableType::AdminUser, $fresh->consentable_type);
        $this->assertSame(ConsentType::Location, $fresh->consent_type);
        $this->assertNotNull($fresh->accepted_at);
    }
}
