<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Domain\Model;

final readonly class SiteScore
{
    public function __construct(
        public int $rootPageUid,
        public float $averageScore,
        public int $pageCount,
        public int $criticalCount,
        public int $needsImprovementCount,
        public int $aiReadyCount,
    ) {}
}
