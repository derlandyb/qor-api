<?php

namespace QOR\App\Domain\Shared;

/**
 * Framework-agnostic port (ARCHITECTURE.md §8.5 — zero framework dependency
 * in the domain layer) for emitting domain events from a use case. The
 * composition root binds this to an adapter that forwards to whatever
 * event-driven mechanism the framework provides (Laravel's dispatcher, in
 * this codebase) — the domain layer only knows it can publish a plain event
 * object, never how it gets delivered.
 */
interface DomainEventPublisher
{
    public function publish(object $event): void;
}
