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
    }
}
