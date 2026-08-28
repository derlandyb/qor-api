<?php

namespace QOR\App\Domain\Promoter\UseCase;

use InvalidArgumentException;
use QOR\App\Domain\Admin\AdminAccount;
use QOR\App\Domain\Admin\AdminAccountRepository;
use QOR\App\Domain\Promoter\Promoter;
use QOR\App\Domain\Promoter\PromoterRepository;
use QOR\App\Domain\Shared\PasswordHasher;
use QOR\App\Domain\User\ConsentRepository;
use QOR\App\Domain\User\Enum\ConsentableType;
use QOR\App\Domain\User\Enum\ConsentType;
use QOR\App\Domain\User\PasswordPolicy;

final class RegisterPromoter
{
    public function __construct(
        private readonly AdminAccountRepository $adminAccounts,
        private readonly PromoterRepository $promoters,
        private readonly ConsentRepository $consent,
        private readonly PasswordHasher $passwordHasher,
        private readonly PasswordPolicy $passwordPolicy,
    ) {
    }

    public function execute(
        string $name,
        string $contactPhone,
        string $contactEmail,
        ?string $instagram,
        ?string $tiktok,
        string $registrationEmail,
        string $password,
        string $consentPolicyVersion,
    ): Promoter {
        if ($consentPolicyVersion === '') {
            throw new InvalidArgumentException('É necessário aceitar os termos de uso.');
        }

        if ($this->adminAccounts->findByEmail($registrationEmail) !== null) {
            throw new InvalidArgumentException('Este e-mail já está cadastrado');
        }

        $errors = $this->passwordPolicy->validate($password);
        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $savedAccount = $this->adminAccounts->save(new AdminAccount(
            id: null,
            name: $name,
            email: $registrationEmail,
            passwordHash: $this->passwordHasher->hash($password),
        ));

        $savedPromoter = $this->promoters->save(new Promoter(
            id: null,
            userId: (int) $savedAccount->id,
            name: $name,
            contactPhone: $contactPhone,
            contactEmail: $contactEmail,
            instagram: $instagram,
            tiktok: $tiktok,
        ));

        $this->consent->record(
            ConsentableType::AdminUser,
            (int) $savedAccount->id,
            ConsentType::Terms,
            $consentPolicyVersion,
        );

        return $savedPromoter;
    }
}
