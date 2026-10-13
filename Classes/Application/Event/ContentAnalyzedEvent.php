<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Application\Event;

use Koh\ContentIntelligence\Domain\Model\AnalysisResult;
use Koh\ContentIntelligence\Domain\Model\ContentSnapshot;

final readonly class ContentAnalyzedEvent
{
    /**
     * @param AnalysisResult[] $results
     */
    public function __construct(
        public ContentSnapshot $snapshot,
        public array $results,
    ) {}
}
