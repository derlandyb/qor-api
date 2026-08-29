<?php

namespace QOR\App\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use QOR\App\Domain\Shared\TransactionManager;

class LaravelTransactionManager implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        return DB::transaction(static fn () => $callback());
    }
}
