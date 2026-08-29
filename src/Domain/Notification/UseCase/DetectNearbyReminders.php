<?php

namespace QOR\App\Domain\Notification\UseCase;

use DateTimeImmutable;
use QOR\App\Domain\Notification\Enum\NotificationTriggerType;
use QOR\App\Domain\Notification\NotificationDispatcher;
use QOR\App\Domain\Social\FavoriteRepository;
use QOR\App\Domain\User\UserAddressRepository;

/**
 * Scheduled job (NOTIF-01–NOTIF-04): notifies fans whose favorited events are
 * approaching within the configured lead time. Dedup/global-silence/opt-out
 * enforcement is entirely NotificationDispatcher's responsibility — this use
 * case only decides *which* fan+event pairs are candidates.
 */
final class DetectNearbyReminders
{
    public function __construct(
        private readonly UserAddressRepository $addresses,
        private readonly FavoriteRepository $favorites,
        private readonly NotificationDispatcher $dispatcher,
        // Default mirrors config('qor.notifications.nearby_reminder_lead_hours')'s own
        // default (24) — the domain layer can't call config() directly (§8.5); the real
        // value is wired at the composition root (scheduled-job binding, T85), same
        // pattern as NotificationDispatcher's $consolidationWindowMinutes.
        private readonly int $leadHours = 24,
    ) {
    }

    public function execute(): void
    {
        $now = new DateTimeImmutable();

        foreach ($this->addresses->listAllWithAddress() as $address) {
            if ($address->radiusKm === null) {
                continue;
            }

            foreach ($this->favorites->listForUser($address->userId, null)->items as $event) {
                $hoursUntilStart = ($event->startsAt->getTimestamp() - $now->getTimestamp()) / 3600;

                if ($hoursUntilStart < 0 || $hoursUntilStart > $this->leadHours) {
                    continue;
                }

                $this->dispatcher->dispatch(
                    $address->userId,
                    NotificationTriggerType::NearbyReminder,
                    $event->id,
                    ['eventId' => $event->id, 'title' => $event->title],
                );
            }
        }
    }
}
