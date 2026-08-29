<?php

namespace QOR\App\Domain\Approval\UseCase;

use DateTimeImmutable;
use InvalidArgumentException;
use QOR\App\Domain\Approval\ApprovalDecision;
use QOR\App\Domain\Approval\ApprovalDecisionRepository;
use QOR\App\Domain\Approval\Enum\ApprovalDecidableType;
use QOR\App\Domain\Approval\Enum\ApprovalOutcome;
use QOR\App\Domain\Event\DomainEvent\EventCancelled;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Event\EventRepository;
use QOR\App\Domain\Shared\DomainEventPublisher;

final class DecideEventApproval
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly ApprovalDecisionRepository $approvalDecisionRepository,
        private readonly DomainEventPublisher $domainEvents,
    ) {
    }

    public function execute(
        int $eventId,
        ApprovalOutcome $outcome,
        ?string $feedback,
        int $decidedBy,
    ): ApprovalDecision {
        if (! in_array($outcome, [ApprovalOutcome::Approved, ApprovalOutcome::Rejected, ApprovalOutcome::ForceCancelled], true)) {
            throw new InvalidArgumentException('Este caso de uso não trata este tipo de decisão para eventos.');
        }

        $event = $this->eventRepository->findById($eventId);

        if ($event === null) {
            throw new InvalidArgumentException('Evento não encontrado.');
        }

        $newStatus = match ($outcome) {
            ApprovalOutcome::Approved => $event->startsAt < new DateTimeImmutable()
                ? EventStatus::Ended
                : EventStatus::Published,
            ApprovalOutcome::Rejected => EventStatus::Draft,
            ApprovalOutcome::ForceCancelled => EventStatus::Cancelled,
        };

        $transitionedEvent = $event->transitionTo($newStatus, $feedback);

        $this->eventRepository->save($transitionedEvent);

        // NOTIF-06: a Super-Admin force-cancel notifies favoriting fans just
        // like an organizer-initiated cancellation (CancelEvent, T44) —
        // regardless of who initiated it.
        if ($outcome === ApprovalOutcome::ForceCancelled) {
            $this->domainEvents->publish(new EventCancelled($eventId));
        }

        return $this->approvalDecisionRepository->save(new ApprovalDecision(
            id: null,
            decidableType: ApprovalDecidableType::Event,
            decidableId: $eventId,
            outcome: $outcome,
            decidedBy: $decidedBy,
            decidedAt: new DateTimeImmutable(),
            reason: $feedback,
        ));
    }
}
