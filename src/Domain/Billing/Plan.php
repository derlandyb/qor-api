<?php

namespace QOR\App\Domain\Billing;

use InvalidArgumentException;

final class Plan
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly float $monthlyPrice,
        public readonly ?float $annualPrice,
        public readonly ?int $publishQuota,
        public readonly bool $isActive = true,
        public readonly bool $isDefaultFree = false,
    ) {
        if ($this->name === '') {
            throw new InvalidArgumentException('O nome do plano não pode ser vazio.');
        }

        if ($this->monthlyPrice < 0) {
            throw new InvalidArgumentException('O preço mensal do plano não pode ser negativo.');
        }

        if ($this->annualPrice !== null && $this->annualPrice < 0) {
            throw new InvalidArgumentException('O preço anual do plano não pode ser negativo.');
        }

        if ($this->publishQuota !== null && $this->publishQuota < 0) {
            throw new InvalidArgumentException('A cota de publicações do plano não pode ser negativa.');
        }
    }
}
