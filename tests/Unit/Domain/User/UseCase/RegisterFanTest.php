<?php

namespace Tests\Unit\Domain\User\UseCase;

use DateTimeImmutable;
use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Shared\PasswordHasher;
use QOR\App\Domain\User\ConsentRecord;
use QOR\App\Domain\User\ConsentRepository;
use QOR\App\Domain\User\EmailVerificationPort;
use QOR\App\Domain\User\Enum\ConsentableType;
use QOR\App\Domain\User\Enum\ConsentType;
use QOR\App\Domain\User\PasswordPolicy;
use QOR\App\Domain\User\User;
use QOR\App\Domain\User\UseCase\RegisterFan;
use QOR\App\Domain\User\UserRepository;

class RegisterFanTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function makeUseCase(
        UserRepository $users,
        ?ConsentRepository $consent = null,
        ?PasswordHasher $passwordHasher = null,
        ?PasswordPolicy $passwordPolicy = null,
        ?EmailVerificationPort $emailVerification = null,
    ): RegisterFan {
        return new RegisterFan(
            $users,
            $consent ?? $this->passthroughConsent(),
            $passwordHasher ?? $this->passthroughHasher(),
            $passwordPolicy ?? new PasswordPolicy(minLength: 8, requireMixedCase: true, requireNumbers: true),
            $emailVerification ?? $this->passthroughEmailVerification(),
        );
    }

    private function passthroughConsent(): ConsentRepository
    {
        $consent = Mockery::mock(ConsentRepository::class);
        $consent->shouldReceive('record')->andReturn(new ConsentRecord(
            id: 1,
            consentableType: ConsentableType::User,
            consentableId: 1,
            consentType: ConsentType::Terms,
            policyVersion: '1.0',
            acceptedAt: new DateTimeImmutable(),
        ));

        return $consent;
    }

    private function passthroughEmailVerification(): EmailVerificationPort
    {
        $port = Mockery::mock(EmailVerificationPort::class);
        $port->shouldReceive('send');

        return $port;
    }

    private function passthroughHasher(): PasswordHasher
    {
        $hasher = Mockery::mock(PasswordHasher::class);
        $hasher->shouldReceive('hash')->andReturnUsing(fn (string $plain) => "hashed:{$plain}");

        return $hasher;
    }

    public function test_GIVEN_valid_fields_WHEN_registering_THEN_it_creates_an_unverified_account_records_consent_and_sends_verification(): void
    {
        $users = Mockery::mock(UserRepository::class);
        $users->shouldReceive('findByEmail')->once()->with('ana@example.com')->andReturnNull();
        $users->shouldReceive('save')->once()->andReturnUsing(fn (User $user) => new User(
            id: 1,
            name: $user->name,
            email: $user->email,
            birthdate: $user->birthdate,
            passwordHash: $user->passwordHash,
            phone: $user->phone,
        ));

        $consent = Mockery::mock(ConsentRepository::class);
        $consent->shouldReceive('record')->once()
            ->with(ConsentableType::User, 1, ConsentType::Terms, '1.0')
            ->andReturn(new ConsentRecord(
                id: 1,
                consentableType: ConsentableType::User,
                consentableId: 1,
                consentType: ConsentType::Terms,
                policyVersion: '1.0',
                acceptedAt: new DateTimeImmutable(),
            ));

        $emailVerification = Mockery::mock(EmailVerificationPort::class);
        $emailVerification->shouldReceive('send')->once()->with(1);

        $useCase = $this->makeUseCase($users, $consent, emailVerification: $emailVerification);

        $saved = $useCase->execute(
            name: 'Ana Silva',
            email: 'ana@example.com',
            password: 'Senha123',
            birthdate: new DateTimeImmutable('2000-01-01'),
            phone: null,
            consentPolicyVersion: '1.0',
        );

        $this->assertFalse($saved->isVerified());
        $this->assertSame('hashed:Senha123', $saved->passwordHash);
    }

    public function test_GIVEN_an_email_already_registered_WHEN_registering_THEN_it_rejects_with_the_duplicate_message(): void
    {
        $users = Mockery::mock(UserRepository::class);
        $users->shouldReceive('findByEmail')->once()->andReturn(new User(
            id: 1,
            name: 'Existing',
            email: 'ana@example.com',
            birthdate: new DateTimeImmutable('2000-01-01'),
            passwordHash: 'hash',
        ));
        $users->shouldNotReceive('save');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Este e-mail já está cadastrado');

        $this->makeUseCase($users)->execute(
            name: 'Ana Silva',
            email: 'ana@example.com',
            password: 'Senha123',
            birthdate: new DateTimeImmutable('2000-01-01'),
            phone: null,
            consentPolicyVersion: '1.0',
        );
    }

    public function test_GIVEN_a_weak_password_WHEN_registering_THEN_it_rejects_with_a_specific_reason(): void
    {
        $users = Mockery::mock(UserRepository::class);
        $users->shouldReceive('findByEmail')->once()->andReturnNull();
        $users->shouldNotReceive('save');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mínimo');

        $this->makeUseCase($users)->execute(
            name: 'Ana Silva',
            email: 'ana@example.com',
            password: 'abc',
            birthdate: new DateTimeImmutable('2000-01-01'),
            phone: null,
            consentPolicyVersion: '1.0',
        );
    }

    public function test_GIVEN_no_consent_acceptance_WHEN_registering_THEN_it_rejects_before_any_row_is_created(): void
    {
        $users = Mockery::mock(UserRepository::class);
        $users->shouldNotReceive('findByEmail');
        $users->shouldNotReceive('save');

        $consent = Mockery::mock(ConsentRepository::class);
        $consent->shouldNotReceive('record');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('termos de uso');

        $this->makeUseCase($users, $consent)->execute(
            name: 'Ana Silva',
            email: 'ana@example.com',
            password: 'Senha123',
            birthdate: new DateTimeImmutable('2000-01-01'),
            phone: null,
            consentPolicyVersion: '',
        );
    }

    public function test_GIVEN_a_phone_number_WHEN_registering_THEN_it_is_persisted_on_the_saved_user(): void
    {
        $users = Mockery::mock(UserRepository::class);
        $users->shouldReceive('findByEmail')->once()->andReturnNull();
        $users->shouldReceive('save')->once()->andReturnUsing(fn (User $user) => new User(
            id: 2,
            name: $user->name,
            email: $user->email,
            birthdate: $user->birthdate,
            passwordHash: $user->passwordHash,
            phone: $user->phone,
        ));

        $saved = $this->makeUseCase($users)->execute(
            name: 'Bia Costa',
            email: 'bia@example.com',
            password: 'Senha123',
            birthdate: new DateTimeImmutable('1998-04-10'),
            phone: '27999999999',
            consentPolicyVersion: '1.0',
        );

        $this->assertSame('27999999999', $saved->phone);
    }

    public function test_GIVEN_registration_succeeds_WHEN_registering_THEN_the_verification_email_is_sent_for_the_new_user_id(): void
    {
        $users = Mockery::mock(UserRepository::class);
        $users->shouldReceive('findByEmail')->once()->andReturnNull();
        $users->shouldReceive('save')->once()->andReturnUsing(fn (User $user) => new User(
            id: 7,
            name: $user->name,
            email: $user->email,
            birthdate: $user->birthdate,
            passwordHash: $user->passwordHash,
        ));

        $emailVerification = Mockery::mock(EmailVerificationPort::class);
        $emailVerification->shouldReceive('send')->once()->with(7);

        $this->makeUseCase($users, emailVerification: $emailVerification)->execute(
            name: 'Caio Souza',
            email: 'caio@example.com',
            password: 'Senha123',
            birthdate: new DateTimeImmutable('1995-05-05'),
            phone: null,
            consentPolicyVersion: '1.0',
        );
    }
}
