<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Application\Event;

use Koh\ContentIntelligence\Domain\Model\AuditEntry;

final readonly class AuditEntryCreatedEvent
{
    public function __construct(
        public AuditEntry $entry,
    ) {}
}
