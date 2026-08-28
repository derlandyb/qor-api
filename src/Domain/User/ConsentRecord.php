<?php

namespace QOR\App\Domain\User;

use DateTimeImmutable;
use InvalidArgumentException;
use QOR\App\Domain\User\Enum\ConsentableType;
use QOR\App\Domain\User\Enum\ConsentType;

final class ConsentRecord
{
    public function __construct(
        public readonly ?int $id,
        public readonly ConsentableType $consentableType,
        public readonly int $consentableId,
        public readonly ConsentType $consentType,
        public readonly string $policyVersion,
        public readonly DateTimeImmutable $acceptedAt,
    ) {
        if ($this->policyVersion === '') {
            throw new InvalidArgumentException('A versão da política não pode ser vazia.');
        }
    }
}
