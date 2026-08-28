<?php

namespace QOR\App\Domain\User;

final class PasswordPolicy
{
    public function __construct(
        private readonly int $minLength,
        private readonly bool $requireMixedCase,
        private readonly bool $requireNumbers,
    ) {
    }

    /**
     * @return list<string> pt-BR reasons the password fails the policy; empty when valid.
     */
    public function validate(string $password): array
    {
        $errors = [];

        if (mb_strlen($password) < $this->minLength) {
            $errors[] = "A senha precisa ter no mínimo {$this->minLength} caracteres.";
        }

        if ($this->requireMixedCase && ! (preg_match('/[a-z]/', $password) && preg_match('/[A-Z]/', $password))) {
            $errors[] = 'A senha precisa conter letras maiúsculas e minúsculas.';
        }

        if ($this->requireNumbers && ! preg_match('/[0-9]/', $password)) {
            $errors[] = 'A senha precisa conter pelo menos um número.';
        }

        return $errors;
    }
}
