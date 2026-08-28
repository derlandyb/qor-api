<?php

namespace QOR\App\Domain\User\UseCase;

use InvalidArgumentException;
use QOR\App\Domain\User\ConsentRecord;
use QOR\App\Domain\User\ConsentRepository;
use QOR\App\Domain\User\Enum\ConsentableType;
use QOR\App\Domain\User\Enum\ConsentType;
use QOR\App\Domain\User\User;
use QOR\App\Domain\User\UserRepository;

/**
 * LGPD data-subject rights (AUTH-25): access, delete, export, revoke consent.
 * Reference implementation per ARCHITECTURE.md §7 — other roles get the
 * equivalent later.
 */
final class ExerciseDataRight
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ConsentRepository $consent,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function access(int $userId): array
    {
        return $this->summarize($this->requireUser($userId));
    }

    public function export(int $userId): string
    {
        return json_encode(
            $this->summarize($this->requireUser($userId)),
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT,
        );
    }

    /**
     * Soft-deletes + cascades (ARCHITECTURE.md §7) — the repository's delete()
     * already implements the soft-delete/PII-scrub contract.
     */
    public function delete(int $userId): void
    {
        $this->requireUser($userId);
        $this->users->delete($userId);
    }

    /**
     * Stops the corresponding data use immediately, without deleting the account.
     */
    public function revokeConsent(int $userId, ConsentType $consentType): void
    {
        $this->requireUser($userId);
        $this->consent->revoke(ConsentableType::User, $userId, $consentType);
    }

    private function requireUser(int $userId): User
    {
        $user = $this->users->findById($userId);

        if ($user === null) {
            throw new InvalidArgumentException('Usuário não encontrado.');
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(User $user): array
    {
        $consents = $this->consent->findAllForConsentable(ConsentableType::User, (int) $user->id);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'birthdate' => $user->birthdate->format('Y-m-d'),
            'profile_picture_url' => $user->profilePictureUrl,
            'email_verified' => $user->isVerified(),
            'consents' => array_map(
                fn (ConsentRecord $record) => [
                    'type' => $record->consentType->value,
                    'policy_version' => $record->policyVersion,
                    'accepted_at' => $record->acceptedAt->format(DATE_ATOM),
                    'revoked_at' => $record->revokedAt?->format(DATE_ATOM),
                ],
                $consents,
            ),
        ];
    }
}
