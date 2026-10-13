<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Application\Event;

use Koh\ContentIntelligence\Domain\Model\ContentSnapshot;

final class BeforeAnalysisEvent
{
    private bool $cancelled = false;

    public function __construct(
        private readonly ContentSnapshot $snapshot,
    ) {}

    public function getSnapshot(): ContentSnapshot
    {
        return $this->snapshot;
    }

    public function cancel(): void
    {
        $this->cancelled = true;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }
}
