<?php

namespace QOR\App\Domain\Notification\UseCase;

use DateTimeImmutable;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Notification\Enum\NotificationTriggerType;
use QOR\App\Domain\Notification\NotificationDispatcher;
use QOR\App\Domain\User\UserAddressRepository;

/**
 * Scheduled job (NOTIF-16–NOTIF-19): batches every event newly Published in a
 * fan's city, within the last `batchWindowMinutes`, into a single digest
 * dispatch call per fan per scan (resolved decision: batch, not one
 * notification per event). Fans without an address/radius are skipped.
 * Cities are the only geographic signal on Event (no lat/long), so "within
 * radius" is approximated by city match — consistent with the single-region,
 * 4-city MVP scope.
 */
final class DetectRegionalPublishes
{
    public function __construct(
        private readonly UserAddressRepository $addresses,
        private readonly EventRepository $events,
        private readonly NotificationDispatcher $dispatcher,
        private readonly int $batchWindowMinutes = 60,
    ) {
    }

    public function execute(): void
    {
        $since = (new DateTimeImmutable())->modify("-{$this->batchWindowMinutes} minutes");

        foreach ($this->addresses->listAllWithAddress() as $address) {
            if ($address->radiusKm === null) {
                continue;
            }

            $events = $this->events->findRecentlyPublished($address->city, $since);

            if ($events === []) {
                continue;
            }

            $eventIds = array_map(fn ($event) => $event->id, $events);

            $this->dispatcher->dispatch(
                $address->userId,
                NotificationTriggerType::NewRegional,
                null,
                ['eventIds' => $eventIds],
            );
        }
    }
}
