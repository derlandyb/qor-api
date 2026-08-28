<?php

namespace Tests\Unit\Domain\Promoter\UseCase;

use DateTimeImmutable;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Admin\AdminAccount;
use QOR\App\Domain\Admin\AdminAccountRepository;
use QOR\App\Domain\Approval\Enum\ApprovalStatus;
use QOR\App\Domain\Promoter\Promoter;
use QOR\App\Domain\Promoter\PromoterRepository;
use QOR\App\Domain\Promoter\UseCase\RegisterPromoter;
use QOR\App\Domain\Shared\PasswordHasher;
use QOR\App\Domain\User\ConsentRepository;
use QOR\App\Domain\User\Enum\ConsentableType;
use QOR\App\Domain\User\Enum\ConsentType;
use QOR\App\Domain\User\PasswordPolicy;

class RegisterPromoterTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_GIVEN_valid_data_WHEN_executing_THEN_admin_account_promoter_and_consent_are_created(): void
    {
        $adminAccounts = Mockery::mock(AdminAccountRepository::class);
        $promoters = Mockery::mock(PromoterRepository::class);
        $consent = Mockery::mock(ConsentRepository::class);
        $passwordHasher = Mockery::mock(PasswordHasher::class);
        $passwordPolicy = new PasswordPolicy(minLength: 8, requireMixedCase: true, requireNumbers: true);

        $adminAccounts->shouldReceive('findByEmail')
            ->once()
            ->with('promoter@example.com')
            ->andReturn(null);

        $passwordHasher->shouldReceive('hash')
            ->once()
            ->with('SenhaForte1')
            ->andReturn('hashed-password');

        $adminAccounts->shouldReceive('save')
            ->once()
            ->with(Mockery::on(function (AdminAccount $account) {
                return $account->id === null
                    && $account->name === 'Produtora XYZ'
                    && $account->email === 'promoter@example.com'
                    && $account->passwordHash === 'hashed-password'
                    && $account->isSuperAdmin === false;
            }))
            ->andReturn(new AdminAccount(
                id: 42,
                name: 'Produtora XYZ',
                email: 'promoter@example.com',
                passwordHash: 'hashed-password',
            ));

        $promoters->shouldReceive('save')
            ->once()
            ->with(Mockery::on(function (Promoter $promoter) {
                return $promoter->id === null
                    && $promoter->userId === 42
                    && $promoter->name === 'Produtora XYZ'
                    && $promoter->contactPhone === '27999998888'
                    && $promoter->contactEmail === 'contato@produtora.com'
                    && $promoter->approvalStatus === ApprovalStatus::PendingApproval
                    && $promoter->instagram === '@produtora'
                    && $promoter->tiktok === null;
            }))
            ->andReturnUsing(function (Promoter $promoter) {
                return new Promoter(
                    id: 7,
                    userId: $promoter->userId,
                    name: $promoter->name,
                    contactPhone: $promoter->contactPhone,
                    contactEmail: $promoter->contactEmail,
                    instagram: $promoter->instagram,
                    tiktok: $promoter->tiktok,
                );
            });

        $consent->shouldReceive('record')
            ->once()
            ->with(ConsentableType::AdminUser, 42, ConsentType::Terms, 'v1')
            ->andReturn(new \QOR\App\Domain\User\ConsentRecord(
                id: 1,
                consentableType: ConsentableType::AdminUser,
                consentableId: 42,
                consentType: ConsentType::Terms,
                policyVersion: 'v1',
                acceptedAt: new DateTimeImmutable(),
            ));

        $useCase = new RegisterPromoter($adminAccounts, $promoters, $consent, $passwordHasher, $passwordPolicy);

        $result = $useCase->execute(
            name: 'Produtora XYZ',
            contactPhone: '27999998888',
            contactEmail: 'contato@produtora.com',
            instagram: '@produtora',
            tiktok: null,
            registrationEmail: 'promoter@example.com',
            password: 'SenhaForte1',
            consentPolicyVersion: 'v1',
        );

        $this->assertSame(7, $result->id);
        $this->assertSame(ApprovalStatus::PendingApproval, $result->approvalStatus);
    }

    public function test_GIVEN_duplicate_registration_email_WHEN_executing_THEN_it_is_rejected_before_any_row_created(): void
    {
        $adminAccounts = Mockery::mock(AdminAccountRepository::class);
        $promoters = Mockery::mock(PromoterRepository::class);
        $consent = Mockery::mock(ConsentRepository::class);
        $passwordHasher = Mockery::mock(PasswordHasher::class);
        $passwordPolicy = new PasswordPolicy(minLength: 8, requireMixedCase: true, requireNumbers: true);

        $adminAccounts->shouldReceive('findByEmail')
            ->once()
            ->with('promoter@example.com')
            ->andReturn(new AdminAccount(
                id: 1,
                name: 'Já Existe',
                email: 'promoter@example.com',
                passwordHash: 'hash',
            ));

        $adminAccounts->shouldNotReceive('save');
        $promoters->shouldNotReceive('save');
        $consent->shouldNotReceive('record');
        $passwordHasher->shouldNotReceive('hash');

        $useCase = new RegisterPromoter($adminAccounts, $promoters, $consent, $passwordHasher, $passwordPolicy);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Este e-mail já está cadastrado');

        $useCase->execute(
            name: 'Produtora XYZ',
            contactPhone: '27999998888',
            contactEmail: 'contato@produtora.com',
            instagram: null,
            tiktok: null,
            registrationEmail: 'promoter@example.com',
            password: 'SenhaForte1',
            consentPolicyVersion: 'v1',
        );
    }

    public function test_GIVEN_empty_consent_policy_version_WHEN_executing_THEN_it_is_rejected_before_any_row_created(): void
    {
        $adminAccounts = Mockery::mock(AdminAccountRepository::class);
        $promoters = Mockery::mock(PromoterRepository::class);
        $consent = Mockery::mock(ConsentRepository::class);
        $passwordHasher = Mockery::mock(PasswordHasher::class);
        $passwordPolicy = new PasswordPolicy(minLength: 8, requireMixedCase: true, requireNumbers: true);

        $adminAccounts->shouldNotReceive('findByEmail');
        $adminAccounts->shouldNotReceive('save');
        $promoters->shouldNotReceive('save');
        $consent->shouldNotReceive('record');
        $passwordHasher->shouldNotReceive('hash');

        $useCase = new RegisterPromoter($adminAccounts, $promoters, $consent, $passwordHasher, $passwordPolicy);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('É necessário aceitar os termos de uso.');

        $useCase->execute(
            name: 'Produtora XYZ',
            contactPhone: '27999998888',
            contactEmail: 'contato@produtora.com',
            instagram: null,
            tiktok: null,
            registrationEmail: 'promoter@example.com',
            password: 'SenhaForte1',
            consentPolicyVersion: '',
        );
    }

    public function test_GIVEN_a_weak_password_WHEN_executing_THEN_it_is_rejected_before_any_row_created(): void
    {
        $adminAccounts = Mockery::mock(AdminAccountRepository::class);
        $promoters = Mockery::mock(PromoterRepository::class);
        $consent = Mockery::mock(ConsentRepository::class);
        $passwordHasher = Mockery::mock(PasswordHasher::class);
        $passwordPolicy = new PasswordPolicy(minLength: 8, requireMixedCase: true, requireNumbers: true);

        $adminAccounts->shouldReceive('findByEmail')
            ->once()
            ->with('promoter@example.com')
            ->andReturn(null);

        $adminAccounts->shouldNotReceive('save');
        $promoters->shouldNotReceive('save');
        $consent->shouldNotReceive('record');
        $passwordHasher->shouldNotReceive('hash');

        $useCase = new RegisterPromoter($adminAccounts, $promoters, $consent, $passwordHasher, $passwordPolicy);

        $this->expectException(InvalidArgumentException::class);

        $useCase->execute(
            name: 'Produtora XYZ',
            contactPhone: '27999998888',
            contactEmail: 'contato@produtora.com',
            instagram: null,
            tiktok: null,
            registrationEmail: 'promoter@example.com',
            password: 'fraca',
            consentPolicyVersion: 'v1',
        );
    }

    public function test_GIVEN_invalid_promoter_fields_WHEN_executing_THEN_promoters_own_validation_error_is_surfaced_before_any_row_is_created(): void
    {
        $adminAccounts = Mockery::mock(AdminAccountRepository::class);
        $promoters = Mockery::mock(PromoterRepository::class);
        $consent = Mockery::mock(ConsentRepository::class);
        $passwordHasher = Mockery::mock(PasswordHasher::class);
        $passwordPolicy = new PasswordPolicy(minLength: 8, requireMixedCase: true, requireNumbers: true);

        $adminAccounts->shouldReceive('findByEmail')
            ->once()
            ->with('promoter@example.com')
            ->andReturn(null);

        $adminAccounts->shouldNotReceive('save');
        $promoters->shouldNotReceive('save');
        $consent->shouldNotReceive('record');

        $useCase = new RegisterPromoter($adminAccounts, $promoters, $consent, $passwordHasher, $passwordPolicy);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('E-mail de contato inválido: nao-e-um-email');

        $useCase->execute(
            name: 'Produtora XYZ',
            contactPhone: '27999998888',
            contactEmail: 'nao-e-um-email',
            instagram: null,
            tiktok: null,
            registrationEmail: 'promoter@example.com',
            password: 'SenhaForte1',
            consentPolicyVersion: 'v1',
        );
    }
}
