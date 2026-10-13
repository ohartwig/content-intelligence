<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Application\Event;

/**
 * Mutable companion holder for the stoppable / cancellable events in this
 * package. Lets the events themselves stay `final readonly` while still
 * exposing a `cancel()` API — listeners flip the flag, dispatcher reads it.
 *
 * Not for public use; instantiated by the dispatching service and handed to
 * the event constructor.
 */
final class EventStopFlag
{
    private bool $stopped = false;

    public function stop(): void
    {
        $this->stopped = true;
    }

    public function isStopped(): bool
    {
        return $this->stopped;
    }
}
