<?php

namespace Tests\Unit\Domain\Venue\UseCase;

use DateTimeImmutable;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Admin\AdminAccount;
use QOR\App\Domain\Admin\AdminAccountRepository;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\Shared\PasswordHasher;
use QOR\App\Domain\User\ConsentRecord;
use QOR\App\Domain\User\ConsentRepository;
use QOR\App\Domain\User\Enum\ConsentableType;
use QOR\App\Domain\User\Enum\ConsentType;
use QOR\App\Domain\User\PasswordPolicy;
use QOR\App\Domain\Venue\UseCase\RegisterVenue;
use QOR\App\Domain\Venue\Venue;
use QOR\App\Domain\Venue\VenueRepository;

class RegisterVenueTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function makeUseCase(
        AdminAccountRepository $adminAccounts,
        ?VenueRepository $venues = null,
        ?ConsentRepository $consent = null,
        ?PasswordHasher $passwordHasher = null,
        ?PasswordPolicy $passwordPolicy = null,
    ): RegisterVenue {
        return new RegisterVenue(
            $adminAccounts,
            $venues ?? $this->passthroughVenues(),
            $consent ?? $this->passthroughConsent(),
            $passwordHasher ?? $this->passthroughHasher(),
            $passwordPolicy ?? new PasswordPolicy(minLength: 8, requireMixedCase: true, requireNumbers: true),
        );
    }

    private function passthroughVenues(): VenueRepository
    {
        $venues = Mockery::mock(VenueRepository::class);
        $venues->shouldReceive('save')->andReturnUsing(fn (Venue $venue) => new Venue(
            id: 1,
            venueAdminUserId: $venue->venueAdminUserId,
            name: $venue->name,
            description: $venue->description,
            address: $venue->address,
            city: $venue->city,
            contactPhone: $venue->contactPhone,
            contactEmail: $venue->contactEmail,
            approvalStatus: $venue->approvalStatus,
            imageUrl: $venue->imageUrl,
        ));

        return $venues;
    }

    private function passthroughConsent(): ConsentRepository
    {
        $consent = Mockery::mock(ConsentRepository::class);
        $consent->shouldReceive('record')->andReturn(new ConsentRecord(
            id: 1,
            consentableType: ConsentableType::AdminUser,
            consentableId: 1,
            consentType: ConsentType::Terms,
            policyVersion: '1.0',
            acceptedAt: new DateTimeImmutable(),
        ));

        return $consent;
    }

    private function passthroughHasher(): PasswordHasher
    {
        $hasher = Mockery::mock(PasswordHasher::class);
        $hasher->shouldReceive('hash')->andReturnUsing(fn (string $plain) => "hashed:{$plain}");

        return $hasher;
    }

    private function passthroughAdminAccounts(?int $savedId = 1): AdminAccountRepository
    {
        $adminAccounts = Mockery::mock(AdminAccountRepository::class);
        $adminAccounts->shouldReceive('findByEmail')->once()->andReturnNull();
        $adminAccounts->shouldReceive('save')->once()->andReturnUsing(fn (AdminAccount $account) => new AdminAccount(
            id: $savedId,
            name: $account->name,
            email: $account->email,
            passwordHash: $account->passwordHash,
            isSuperAdmin: $account->isSuperAdmin,
        ));

        return $adminAccounts;
    }

    private function validParams(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Bar do Zé',
            'description' => 'Casa de shows no centro',
            'address' => 'Rua das Flores, 100',
            'city' => City::Vitoria,
            'contactPhone' => '27999999999',
            'contactEmail' => 'contato@bardoze.com',
            'registrationEmail' => 'admin@bardoze.com',
            'password' => 'Senha123',
            'consentPolicyVersion' => '1.0',
        ], $overrides);
    }

    public function test_GIVEN_valid_fields_WHEN_registering_THEN_it_creates_the_admin_account_and_a_pending_venue_and_records_consent(): void
    {
        $adminAccounts = $this->passthroughAdminAccounts();

        $venues = Mockery::mock(VenueRepository::class);
        $venues->shouldReceive('save')->once()->andReturnUsing(function (Venue $venue) {
            $this->assertNull($venue->id);
            $this->assertSame(1, $venue->venueAdminUserId);
            $this->assertSame(ApprovalStatus::PendingApproval, $venue->approvalStatus);

            return new Venue(
                id: 10,
                venueAdminUserId: $venue->venueAdminUserId,
                name: $venue->name,
                description: $venue->description,
                address: $venue->address,
                city: $venue->city,
                contactPhone: $venue->contactPhone,
                contactEmail: $venue->contactEmail,
                approvalStatus: $venue->approvalStatus,
            );
        });

        $consent = Mockery::mock(ConsentRepository::class);
        $consent->shouldReceive('record')->once()
            ->with(ConsentableType::AdminUser, 1, ConsentType::Terms, '1.0')
            ->andReturn(new ConsentRecord(
                id: 1,
                consentableType: ConsentableType::AdminUser,
                consentableId: 1,
                consentType: ConsentType::Terms,
                policyVersion: '1.0',
                acceptedAt: new DateTimeImmutable(),
            ));

        $useCase = $this->makeUseCase($adminAccounts, $venues, $consent);

        $saved = $useCase->execute(...$this->validParams());

        $this->assertSame(10, $saved->id);
        $this->assertSame(ApprovalStatus::PendingApproval, $saved->approvalStatus);
    }

    public function test_GIVEN_a_registration_email_already_registered_WHEN_registering_THEN_it_rejects_before_creating_any_row(): void
    {
        $adminAccounts = Mockery::mock(AdminAccountRepository::class);
        $adminAccounts->shouldReceive('findByEmail')->once()->with('admin@bardoze.com')->andReturn(new AdminAccount(
            id: 1,
            name: 'Existing',
            email: 'admin@bardoze.com',
            passwordHash: 'hash',
        ));
        $adminAccounts->shouldNotReceive('save');

        $venues = Mockery::mock(VenueRepository::class);
        $venues->shouldNotReceive('save');

        $consent = Mockery::mock(ConsentRepository::class);
        $consent->shouldNotReceive('record');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Este e-mail já está cadastrado');

        $this->makeUseCase($adminAccounts, $venues, $consent)->execute(...$this->validParams());
    }

    public function test_GIVEN_no_consent_acceptance_WHEN_registering_THEN_it_rejects_before_any_row_is_created(): void
    {
        $adminAccounts = Mockery::mock(AdminAccountRepository::class);
        $adminAccounts->shouldNotReceive('findByEmail');
        $adminAccounts->shouldNotReceive('save');

        $venues = Mockery::mock(VenueRepository::class);
        $venues->shouldNotReceive('save');

        $consent = Mockery::mock(ConsentRepository::class);
        $consent->shouldNotReceive('record');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('termos de uso');

        $this->makeUseCase($adminAccounts, $venues, $consent)->execute(
            ...$this->validParams(['consentPolicyVersion' => ''])
        );
    }

    public function test_GIVEN_a_weak_password_WHEN_registering_THEN_it_rejects_with_a_specific_reason_before_creating_any_row(): void
    {
        $adminAccounts = Mockery::mock(AdminAccountRepository::class);
        $adminAccounts->shouldReceive('findByEmail')->once()->andReturnNull();
        $adminAccounts->shouldNotReceive('save');

        $venues = Mockery::mock(VenueRepository::class);
        $venues->shouldNotReceive('save');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mínimo');

        $this->makeUseCase($adminAccounts, $venues)->execute(
            ...$this->validParams(['password' => 'abc'])
        );
    }

    public function test_GIVEN_an_empty_venue_name_WHEN_registering_THEN_venues_own_validation_error_is_surfaced_before_any_row_is_created(): void
    {
        $adminAccounts = Mockery::mock(AdminAccountRepository::class);
        $adminAccounts->shouldReceive('findByEmail')->once()->andReturnNull();
        $adminAccounts->shouldNotReceive('save');

        $venues = Mockery::mock(VenueRepository::class);
        $venues->shouldNotReceive('save');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('nome do estabelecimento');

        $this->makeUseCase($adminAccounts, $venues)->execute(
            ...$this->validParams(['name' => ''])
        );
    }

    public function test_GIVEN_an_invalid_contact_email_WHEN_registering_THEN_venues_own_validation_error_is_surfaced_before_any_row_is_created(): void
    {
        $adminAccounts = Mockery::mock(AdminAccountRepository::class);
        $adminAccounts->shouldReceive('findByEmail')->once()->andReturnNull();
        $adminAccounts->shouldNotReceive('save');

        $venues = Mockery::mock(VenueRepository::class);
        $venues->shouldNotReceive('save');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('E-mail de contato inválido');

        $this->makeUseCase($adminAccounts, $venues)->execute(
            ...$this->validParams(['contactEmail' => 'not-an-email'])
        );
    }

    public function test_GIVEN_registration_succeeds_WHEN_registering_THEN_the_venue_admin_password_is_hashed_before_saving(): void
    {
        $adminAccounts = Mockery::mock(AdminAccountRepository::class);
        $adminAccounts->shouldReceive('findByEmail')->once()->andReturnNull();
        $adminAccounts->shouldReceive('save')->once()->andReturnUsing(function (AdminAccount $account) {
            $this->assertSame('hashed:Senha123', $account->passwordHash);

            return new AdminAccount(
                id: 5,
                name: $account->name,
                email: $account->email,
                passwordHash: $account->passwordHash,
            );
        });

        $this->makeUseCase($adminAccounts)->execute(...$this->validParams());
    }
}
