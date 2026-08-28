<?php

namespace QOR\App\Domain\User\UseCase;

use DateTimeImmutable;
use InvalidArgumentException;
use QOR\App\Domain\Shared\PasswordHasher;
use QOR\App\Domain\User\ConsentRepository;
use QOR\App\Domain\User\Enum\ConsentableType;
use QOR\App\Domain\User\Enum\ConsentType;
use QOR\App\Domain\User\Exception\InvalidCredentials;
use QOR\App\Domain\User\Exception\UnverifiedAccount;
use QOR\App\Domain\User\User;
use QOR\App\Domain\User\UserRepository;

final class AuthenticateFan
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ConsentRepository $consent,
        private readonly PasswordHasher $passwordHasher,
    ) {
    }

    public function executeWithPassword(string $email, string $password): User
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || $user->passwordHash === null || ! $this->passwordHasher->verify($password, $user->passwordHash)) {
            throw new InvalidCredentials('Credenciais inválidas');
        }

        if (! $user->isVerified()) {
            throw new UnverifiedAccount('Confirme seu e-mail para continuar');
        }

        return $user;
    }

    /**
     * Google email is always treated as pre-verified (AUTH-08). Logging into
     * an existing account (regardless of whether it was originally created
     * via password or Google) never requires $consentPolicyVersion — only
     * creating a brand-new account does, since AUTH-06 still requires the
     * same explicit terms acceptance as password signup for a first-time
     * Google signup.
     */
    public function executeWithGoogle(
        string $googleEmail,
        string $googleId,
        string $name,
        ?string $profilePictureUrl,
        ?string $consentPolicyVersion = null,
    ): User {
        $existing = $this->users->findByEmail($googleEmail);

        if ($existing !== null) {
            return $existing;
        }

        if ($consentPolicyVersion === null || $consentPolicyVersion === '') {
            throw new InvalidArgumentException('É necessário aceitar os termos de uso.');
        }

        $saved = $this->users->save(new User(
            id: null,
            name: $name,
            email: $googleEmail,
            // Google doesn't supply birthdate; AUTH-06 collects it as a
            // follow-up profile-completion prompt (UpdateProfile), not here.
            birthdate: new DateTimeImmutable(),
            googleId: $googleId,
            profilePictureUrl: $profilePictureUrl,
            emailVerifiedAt: new DateTimeImmutable(),
        ));

        $this->consent->record(ConsentableType::User, (int) $saved->id, ConsentType::Terms, $consentPolicyVersion);

        return $saved;
    }
}
