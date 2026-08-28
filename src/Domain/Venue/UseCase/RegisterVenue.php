<?php

namespace QOR\App\Domain\Venue\UseCase;

use InvalidArgumentException;
use QOR\App\Domain\Admin\AdminAccount;
use QOR\App\Domain\Admin\AdminAccountRepository;
use QOR\App\Domain\Shared\Enum\City;
use QOR\App\Domain\Shared\PasswordHasher;
use QOR\App\Domain\User\ConsentRepository;
use QOR\App\Domain\User\Enum\ConsentableType;
use QOR\App\Domain\User\Enum\ConsentType;
use QOR\App\Domain\User\PasswordPolicy;
use QOR\App\Domain\Venue\Venue;
use QOR\App\Domain\Venue\VenueRepository;

final class RegisterVenue
{
    public function __construct(
        private readonly AdminAccountRepository $adminAccounts,
        private readonly VenueRepository $venues,
        private readonly ConsentRepository $consent,
        private readonly PasswordHasher $passwordHasher,
        private readonly PasswordPolicy $passwordPolicy,
    ) {
    }

    public function execute(
        string $name,
        string $description,
        string $address,
        City $city,
        string $contactPhone,
        string $contactEmail,
        string $registrationEmail,
        string $password,
        string $consentPolicyVersion,
    ): Venue {
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

        // Validate the venue's own field constraints (Venue's constructor)
        // before creating any row, so invalid data never leaves a dangling
        // admin account behind. The placeholder venueAdminUserId is discarded;
        // the real Venue is constructed again below once the account exists.
        new Venue(
            id: null,
            venueAdminUserId: 0,
            name: $name,
            description: $description,
            address: $address,
            city: $city,
            contactPhone: $contactPhone,
            contactEmail: $contactEmail,
        );

        $savedAccount = $this->adminAccounts->save(new AdminAccount(
            id: null,
            name: $name,
            email: $registrationEmail,
            passwordHash: $this->passwordHasher->hash($password),
        ));

        $savedVenue = $this->venues->save(new Venue(
            id: null,
            venueAdminUserId: (int) $savedAccount->id,
            name: $name,
            description: $description,
            address: $address,
            city: $city,
            contactPhone: $contactPhone,
            contactEmail: $contactEmail,
        ));

        $this->consent->record(
            ConsentableType::AdminUser,
            (int) $savedAccount->id,
            ConsentType::Terms,
            $consentPolicyVersion,
        );

        return $savedVenue;
    }
}
