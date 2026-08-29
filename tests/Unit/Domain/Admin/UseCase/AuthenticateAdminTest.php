<?php

namespace Tests\Unit\Domain\Admin\UseCase;

use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use QOR\App\Domain\Admin\AdminAccount;
use QOR\App\Domain\Admin\AdminAccountRepository;
use QOR\App\Domain\Admin\UseCase\AuthenticateAdmin;
use QOR\App\Domain\Shared\PasswordHasher;

class AuthenticateAdminTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function account(string $email = 'admin@example.com', string $passwordHash = 'correct-hash'): AdminAccount
    {
        return new AdminAccount(
            id: 1,
            name: 'Vitória Eventos',
            email: $email,
            passwordHash: $passwordHash,
        );
    }

    private function makeUseCase(AdminAccountRepository $accounts, ?PasswordHasher $hasher = null): AuthenticateAdmin
    {
        return new AuthenticateAdmin(
            $accounts,
            $hasher ?? $this->correctPasswordHasher(),
        );
    }

    private function correctPasswordHasher(): PasswordHasher
    {
        $hasher = Mockery::mock(PasswordHasher::class);
        $hasher->shouldReceive('verify')->andReturnUsing(fn (string $plain) => $plain === 'Senha123');

        return $hasher;
    }

    public function test_GIVEN_correct_credentials_WHEN_authenticating_THEN_it_returns_the_account(): void
    {
        $accounts = Mockery::mock(AdminAccountRepository::class);
        $accounts->shouldReceive('findByEmail')->once()->with('admin@example.com')->andReturn($this->account());

        $account = $this->makeUseCase($accounts)->executeWithPassword('admin@example.com', 'Senha123');

        $this->assertSame('admin@example.com', $account->email);
    }

    public function test_GIVEN_a_wrong_password_WHEN_authenticating_THEN_it_rejects_with_a_generic_message(): void
    {
        $accounts = Mockery::mock(AdminAccountRepository::class);
        $accounts->shouldReceive('findByEmail')->once()->andReturn($this->account());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Credenciais inválidas');

        $this->makeUseCase($accounts)->executeWithPassword('admin@example.com', 'wrong-password');
    }

    public function test_GIVEN_an_unknown_email_WHEN_authenticating_THEN_it_rejects_with_the_same_generic_message(): void
    {
        $accounts = Mockery::mock(AdminAccountRepository::class);
        $accounts->shouldReceive('findByEmail')->once()->andReturnNull();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Credenciais inválidas');

        $this->makeUseCase($accounts)->executeWithPassword('unknown@example.com', 'Senha123');
    }
}
