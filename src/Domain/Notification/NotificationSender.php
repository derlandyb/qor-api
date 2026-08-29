<?php

namespace QOR\App\Domain\Notification;

interface NotificationSender
{
    /**
     * Sends one notification to $userId. The channel is implicit in the
     * implementing adapter (ARCHITECTURE.md §6.1) — no redundant channel
     * parameter here.
     *
     * A malformed payload or provider failure is logged and swallowed, never
     * thrown — the fan-facing request that triggered the dispatch must not fail
     * because of it.
     *
     * @param array<string, mixed> $payload
     */
    public function send(int $userId, array $payload): void;
}
