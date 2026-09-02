<?php

namespace QOR\App\Domain\User\UseCase;

use DateTimeImmutable;
use InvalidArgumentException;
use QOR\App\Domain\Shared\PasswordHasher;
use QOR\App\Domain\User\ConsentRepository;
use QOR\App\Domain\User\Enum\ConsentableType;
use QOR\App\Domain\User\Enum\ConsentType;
use QOR\App\Domain\User\OtpVerificationPort;
use QOR\App\Domain\User\PasswordPolicy;
use QOR\App\Domain\User\User;
use QOR\App\Domain\User\UserRepository;

final class RegisterFan
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ConsentRepository $consent,
        private readonly PasswordHasher $passwordHasher,
        private readonly PasswordPolicy $passwordPolicy,
        private readonly OtpVerificationPort $otpVerification,
    ) {
    }

    public function execute(
        string $name,
        string $email,
        string $password,
        DateTimeImmutable $birthdate,
        ?string $phone,
        string $consentPolicyVersion,
    ): User {
        if ($consentPolicyVersion === '') {
            throw new InvalidArgumentException('É necessário aceitar os termos de uso.');
        }

        if ($this->users->findByEmail($email) !== null) {
            throw new InvalidArgumentException('Este e-mail já está cadastrado');
        }

        $errors = $this->passwordPolicy->validate($password);
        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $saved = $this->users->save(new User(
            id: null,
            name: $name,
            email: $email,
            birthdate: $birthdate,
            passwordHash: $this->passwordHasher->hash($password),
            phone: $phone,
        ));

        $this->consent->record(ConsentableType::User, (int) $saved->id, ConsentType::Terms, $consentPolicyVersion);
        $this->otpVerification->issueEmailVerificationCode($email);

        return $saved;
    }
}
