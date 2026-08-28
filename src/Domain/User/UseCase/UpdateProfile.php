<?php

namespace QOR\App\Domain\User\UseCase;

use InvalidArgumentException;
use QOR\App\Domain\Shared\FileUploadPort;
use QOR\App\Domain\Shared\UploadableFile;
use QOR\App\Domain\User\EmailVerificationPort;
use QOR\App\Domain\User\User;
use QOR\App\Domain\User\UserRepository;

final class UpdateProfile
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly FileUploadPort $fileUpload,
        private readonly EmailVerificationPort $emailVerification,
    ) {
    }

    /**
     * Name/phone/picture apply immediately. A new email is held as a pending
     * change — the current $email stays the active, logged-in address until
     * the new one is verified (AUTH-19).
     */
    public function execute(
        int $userId,
        ?string $name = null,
        ?string $phone = null,
        ?string $newEmail = null,
        ?UploadableFile $picture = null,
    ): User {
        $user = $this->users->findById($userId);

        if ($user === null) {
            throw new InvalidArgumentException('Usuário não encontrado.');
        }

        $profilePictureUrl = $user->profilePictureUrl;
        if ($picture !== null) {
            $profilePictureUrl = $this->fileUpload->upload($picture, 'profile-pictures');
        }

        $pendingEmail = $user->pendingEmail;
        $emailChanged = false;

        if ($newEmail !== null && $newEmail !== $user->email) {
            if ($this->users->findByEmail($newEmail) !== null) {
                throw new InvalidArgumentException('Este e-mail já está cadastrado');
            }

            $pendingEmail = $newEmail;
            $emailChanged = $pendingEmail !== $user->pendingEmail;
        }

        $updated = $this->users->save(new User(
            id: $user->id,
            name: $name ?? $user->name,
            email: $user->email,
            birthdate: $user->birthdate,
            passwordHash: $user->passwordHash,
            googleId: $user->googleId,
            phone: $phone ?? $user->phone,
            profilePictureUrl: $profilePictureUrl,
            emailVerifiedAt: $user->emailVerifiedAt,
            pendingEmail: $pendingEmail,
        ));

        if ($emailChanged) {
            $this->emailVerification->sendForPendingEmail((int) $updated->id);
        }

        return $updated;
    }
}
