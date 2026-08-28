<?php

namespace Tests\Feature\Infrastructure\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use QOR\App\Domain\User\Enum\ConsentableType;
use QOR\App\Domain\User\Enum\ConsentType;
use QOR\App\Infrastructure\Persistence\Eloquent\UserModel;
use QOR\App\Infrastructure\Persistence\EloquentConsentRepository;
use Tests\TestCase;

class EloquentConsentRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_GIVEN_no_prior_record_WHEN_recording_consent_THEN_it_is_persisted_and_active(): void
    {
        $user = UserModel::factory()->create();
        $repository = new EloquentConsentRepository();

        $record = $repository->record(ConsentableType::User, $user->id, ConsentType::Terms, '1.0');

        $this->assertTrue($record->isActive());
        $this->assertDatabaseHas('consent_records', [
            'consentable_type' => ConsentableType::User->value,
            'consentable_id' => $user->id,
            'consent_type' => ConsentType::Terms->value,
            'revoked_at' => null,
        ]);
    }

    public function test_GIVEN_an_accepted_consent_WHEN_checking_has_accepted_THEN_it_returns_true(): void
    {
        $user = UserModel::factory()->create();
        $repository = new EloquentConsentRepository();
        $repository->record(ConsentableType::User, $user->id, ConsentType::Terms, '1.0');

        $this->assertTrue($repository->hasAccepted(ConsentableType::User, $user->id, ConsentType::Terms));
    }

    public function test_GIVEN_no_consent_recorded_WHEN_checking_has_accepted_THEN_it_returns_false(): void
    {
        $user = UserModel::factory()->create();
        $repository = new EloquentConsentRepository();

        $this->assertFalse($repository->hasAccepted(ConsentableType::User, $user->id, ConsentType::Location));
    }

    public function test_GIVEN_terms_and_location_consent_are_separate_records_WHEN_checking_either_THEN_they_do_not_bundle(): void
    {
        $user = UserModel::factory()->create();
        $repository = new EloquentConsentRepository();
        $repository->record(ConsentableType::User, $user->id, ConsentType::Terms, '1.0');

        $this->assertFalse($repository->hasAccepted(ConsentableType::User, $user->id, ConsentType::Location));
    }

    public function test_GIVEN_an_active_consent_WHEN_revoking_THEN_it_immediately_stops_being_accepted(): void
    {
        $user = UserModel::factory()->create();
        $repository = new EloquentConsentRepository();
        $repository->record(ConsentableType::User, $user->id, ConsentType::Location, '1.0');

        $repository->revoke(ConsentableType::User, $user->id, ConsentType::Location);

        $this->assertFalse($repository->hasAccepted(ConsentableType::User, $user->id, ConsentType::Location));
    }

    public function test_GIVEN_an_admin_user_consentable_WHEN_recording_THEN_it_is_tracked_independently_of_fan_users(): void
    {
        $repository = new EloquentConsentRepository();

        $repository->record(ConsentableType::AdminUser, 42, ConsentType::Terms, '1.0');

        $this->assertTrue($repository->hasAccepted(ConsentableType::AdminUser, 42, ConsentType::Terms));
        $this->assertFalse($repository->hasAccepted(ConsentableType::User, 42, ConsentType::Terms));
    }
}
