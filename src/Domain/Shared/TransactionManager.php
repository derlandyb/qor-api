<?php

namespace QOR\App\Domain\Shared;

interface TransactionManager
{
    /**
     * Runs the given callback atomically: either every write it performs is
     * committed, or none of them are.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(callable $callback): mixed;
}
