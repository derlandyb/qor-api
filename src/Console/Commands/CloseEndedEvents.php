<?php

namespace QOR\App\Console\Commands;

use Illuminate\Console\Command;
use QOR\App\Domain\Event\Enum\EventStatus;
use QOR\App\Domain\Event\EventRepository;

/**
 * Naturally ends Published events once their starts_at has passed (ADMIN-23).
 * Scheduled via routes/console.php.
 */
class CloseEndedEvents extends Command
{
    protected $signature = 'events:close-ended';

    protected $description = 'Transition Published events whose start time has passed to Ended.';

    public function __construct(private readonly EventRepository $events)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $events = $this->events->findPublishedPastEnd();

        foreach ($events as $event) {
            $ended = $event->transitionTo(EventStatus::Ended);
            $this->events->save($ended);
        }

        $count = count($events);
        $this->info("Closed {$count} ended events.");

        return Command::SUCCESS;
    }
}
