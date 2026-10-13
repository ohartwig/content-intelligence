<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Domain\Model;

final readonly class AnalysisResult
{
    /**
     * @param array<string, mixed> $details
     * @param string[]             $suggestions
     */
    public function __construct(
        public string $analyzerIdentifier,
        public float $score,
        public array $details,
        public array $suggestions,
    ) {}
}
