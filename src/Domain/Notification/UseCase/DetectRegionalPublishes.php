<?php

namespace QOR\App\Domain\Notification\UseCase;

use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Notification\Enum\NotificationTriggerType;
use QOR\App\Domain\Notification\NotificationDispatcher;
use QOR\App\Domain\User\UserAddressRepository;

/**
 * Scheduled job (NOTIF-16–NOTIF-19): batches every currently-Published event
 * in a fan's city into a single digest dispatch call per fan, per scan
 * (resolved decision: batch, not one notification per event). Fans without
 * an address/radius are skipped. Cities are the only geographic signal on
 * Event (no lat/long), so "within radius" is approximated by city match —
 * consistent with the single-region, 4-city MVP scope.
 */
final class DetectRegionalPublishes
{
    public function __construct(
        private readonly UserAddressRepository $addresses,
        private readonly EventRepository $events,
        private readonly NotificationDispatcher $dispatcher,
    ) {
    }

    public function execute(): void
    {
        foreach ($this->addresses->listAllWithAddress() as $address) {
            if ($address->radiusKm === null) {
                continue;
            }

            $events = $this->events->findUpcoming($address->city, null, null)->items;

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
