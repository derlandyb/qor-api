<?php

namespace QOR\App\Domain\Notification;

interface NotificationSender
{
    /**
     * Sends one notification to $userId. The channel is implicit in the
     * implementing adapter (ARCHITECTURE.md §6.1) — no redundant channel
     * parameter here.
     *
     * @param array<string, mixed> $payload
     *
     * @throws \InvalidArgumentException if $payload is malformed for this channel
     */
    public function send(int $userId, array $payload): void;
}
