<?php

namespace QOR\App\Domain\Admin\UseCase;

use QOR\App\Domain\Admin\AdminAccount;
use QOR\App\Domain\Admin\AdminAccountRepository;
use QOR\App\Domain\Admin\Exception\InvalidCredentials;
use QOR\App\Domain\Shared\PasswordHasher;

final class AuthenticateAdmin
{
    public function __construct(
        private readonly AdminAccountRepository $accounts,
        private readonly PasswordHasher $passwordHasher,
    ) {
    }

    public function executeWithPassword(string $email, string $password): AdminAccount
    {
        $account = $this->accounts->findByEmail($email);

        if ($account === null || ! $this->passwordHasher->verify($password, $account->passwordHash)) {
            throw new InvalidCredentials('Credenciais inválidas');
        }

        return $account;
    }
}
