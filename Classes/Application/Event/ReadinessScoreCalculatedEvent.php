<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Application\Event;

use Koh\ContentIntelligence\Domain\Model\ReadinessScore;

final readonly class ReadinessScoreCalculatedEvent
{
    public function __construct(
        public ReadinessScore $score,
    ) {}
}
