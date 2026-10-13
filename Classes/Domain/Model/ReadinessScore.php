<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Domain\Model;

final readonly class ReadinessScore
{
    /**
     * @param array<string, float> $analyzerScores
     */
    public function __construct(
        public int $pageUid,
        public int $languageUid,
        public float $overallScore,
        public array $analyzerScores,
        public ScoreThreshold $threshold,
        public \DateTimeImmutable $analyzedAt,
    ) {}
}
