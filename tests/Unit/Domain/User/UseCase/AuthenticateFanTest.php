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
use QOR\App\Domain\User\Enum\ConsentableType;
use QOR\App\Domain\User\Enum\ConsentType;
use QOR\App\Domain\User\User;
use QOR\App\Domain\User\UseCase\AuthenticateFan;
use QOR\App\Domain\User\UserRepository;

class AuthenticateFanTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function verifiedUser(string $email = 'ana@example.com', string $passwordHash = 'correct-hash'): User
    {
        return new User(
            id: 1,
            name: 'Ana Silva',
            email: $email,
            birthdate: new DateTimeImmutable('2000-01-01'),
            passwordHash: $passwordHash,
            emailVerifiedAt: new DateTimeImmutable(),
        );
    }

    private function makeUseCase(UserRepository $users, ?ConsentRepository $consent = null, ?PasswordHasher $hasher = null): AuthenticateFan
    {
        return new AuthenticateFan(
            $users,
            $consent ?? Mockery::mock(ConsentRepository::class),
            $hasher ?? $this->correctPasswordHasher(),
        );
    }

    private function correctPasswordHasher(): PasswordHasher
    {
        $hasher = Mockery::mock(PasswordHasher::class);
        $hasher->shouldReceive('verify')->andReturnUsing(fn (string $plain) => $plain === 'Senha123');

        return $hasher;
    }

    public function test_GIVEN_correct_credentials_for_a_verified_account_WHEN_authenticating_THEN_it_returns_the_user(): void
    {
        $users = Mockery::mock(UserRepository::class);
        $users->shouldReceive('findByEmail')->once()->with('ana@example.com')->andReturn($this->verifiedUser());

        $user = $this->makeUseCase($users)->executeWithPassword('ana@example.com', 'Senha123');

        $this->assertSame('ana@example.com', $user->email);
    }

    public function test_GIVEN_a_wrong_password_WHEN_authenticating_THEN_it_rejects_with_a_generic_message(): void
    {
        $users = Mockery::mock(UserRepository::class);
        $users->shouldReceive('findByEmail')->once()->andReturn($this->verifiedUser());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Credenciais inválidas');

        $this->makeUseCase($users)->executeWithPassword('ana@example.com', 'wrong-password');
    }

    public function test_GIVEN_an_unknown_email_WHEN_authenticating_THEN_it_rejects_with_the_same_generic_message(): void
    {
        $users = Mockery::mock(UserRepository::class);
        $users->shouldReceive('findByEmail')->once()->andReturnNull();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Credenciais inválidas');

        $this->makeUseCase($users)->executeWithPassword('unknown@example.com', 'Senha123');
    }

    public function test_GIVEN_an_unverified_account_with_correct_password_WHEN_authenticating_THEN_it_is_blocked_with_a_distinct_message(): void
    {
        $unverified = new User(
            id: 1,
            name: 'Ana Silva',
            email: 'ana@example.com',
            birthdate: new DateTimeImmutable('2000-01-01'),
            passwordHash: 'correct-hash',
        );

        $users = Mockery::mock(UserRepository::class);
        $users->shouldReceive('findByEmail')->once()->andReturn($unverified);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Confirme seu e-mail');

        $this->makeUseCase($users)->executeWithPassword('ana@example.com', 'Senha123');
    }

    public function test_GIVEN_a_new_google_email_WHEN_authenticating_THEN_it_creates_a_pre_verified_pre_filled_account(): void
    {
        $users = Mockery::mock(UserRepository::class);
        $users->shouldReceive('findByEmail')->once()->with('nova@example.com')->andReturnNull();
        $users->shouldReceive('save')->once()->andReturnUsing(fn (User $user) => new User(
            id: 5,
            name: $user->name,
            email: $user->email,
            birthdate: $user->birthdate,
            googleId: $user->googleId,
            profilePictureUrl: $user->profilePictureUrl,
            emailVerifiedAt: $user->emailVerifiedAt,
        ));

        $consent = Mockery::mock(ConsentRepository::class);
        $consent->shouldReceive('record')->once()
            ->with(ConsentableType::User, 5, ConsentType::Terms, '1.0')
            ->andReturn(new ConsentRecord(
                id: 1,
                consentableType: ConsentableType::User,
                consentableId: 5,
                consentType: ConsentType::Terms,
                policyVersion: '1.0',
                acceptedAt: new DateTimeImmutable(),
            ));

        $user = $this->makeUseCase($users, $consent)->executeWithGoogle(
            googleEmail: 'nova@example.com',
            googleId: 'google-123',
            name: 'Nova Fã',
            profilePictureUrl: 'https://example.com/pic.jpg',
            consentPolicyVersion: '1.0',
        );

        $this->assertTrue($user->isVerified());
        $this->assertSame('google-123', $user->googleId);
    }

    public function test_GIVEN_an_existing_google_email_WHEN_authenticating_THEN_it_logs_into_the_existing_account_without_duplicating(): void
    {
        $existing = $this->verifiedUser(email: 'ana@example.com');

        $users = Mockery::mock(UserRepository::class);
        $users->shouldReceive('findByEmail')->once()->with('ana@example.com')->andReturn($existing);
        $users->shouldNotReceive('save');

        $consent = Mockery::mock(ConsentRepository::class);
        $consent->shouldNotReceive('record');

        $user = $this->makeUseCase($users, $consent)->executeWithGoogle(
            googleEmail: 'ana@example.com',
            googleId: 'google-999',
            name: 'Ana Silva',
            profilePictureUrl: null,
        );

        $this->assertSame($existing, $user);
    }
}
