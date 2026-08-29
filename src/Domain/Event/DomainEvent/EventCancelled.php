<?php

namespace QOR\App\Domain\Event\DomainEvent;

/**
 * Emitted by CancelEvent (T44, organizer-initiated) and DecideEventApproval
 * (T39, Super-Admin force-cancel path) when an event is cancelled
 * (NOTIF-05–NOTIF-08). HandleEventChangedOrCancelled (T78) subscribes to
 * this and notifies every favoriting fan, regardless of who initiated the
 * cancellation.
 */
final class EventCancelled
{
    public function __construct(
        public readonly int $eventId,
    ) {
    }
}
