<?php

namespace QOR\App\Domain\Promoter;

interface PromoterRepository
{
    public function findById(int $id): ?Promoter;

    public function save(Promoter $promoter): Promoter;

    public function delete(int $id): void;
}
