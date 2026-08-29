<?php

namespace QOR\App\Infrastructure\Events;

use Illuminate\Contracts\Events\Dispatcher;
use QOR\App\Domain\Shared\DomainEventPublisher;

class LaravelDomainEventPublisher implements DomainEventPublisher
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
    ) {
    }

    public function publish(object $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
