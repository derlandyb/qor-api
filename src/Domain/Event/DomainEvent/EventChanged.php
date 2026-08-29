<?php

namespace QOR\App\Domain\Event\DomainEvent;

/**
 * Emitted by EditEvent (T44) when a Published event's fan-visible fields
 * materially change (NOTIF-05–NOTIF-08). HandleEventChangedOrCancelled
 * (T78) subscribes to this and notifies every favoriting fan.
 */
final class EventChanged
{
    public function __construct(
        public readonly int $eventId,
    ) {
    }
}
