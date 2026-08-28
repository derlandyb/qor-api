<?php

namespace QOR\App\Domain\Admin;

interface AdminAccountRepository
{
    public function findByEmail(string $email): ?AdminAccount;

    public function save(AdminAccount $account): AdminAccount;
}
