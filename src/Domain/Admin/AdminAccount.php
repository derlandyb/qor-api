<?php

namespace QOR\App\Domain\Admin;

use InvalidArgumentException;

final class AdminAccount
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly bool $isSuperAdmin = false,
    ) {
        if (! filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("E-mail inválido: {$this->email}");
        }

        if ($this->name === '') {
            throw new InvalidArgumentException('O nome não pode ser vazio.');
        }
    }
}
