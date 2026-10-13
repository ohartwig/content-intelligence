<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Domain\Contract;

use Koh\ContentIntelligence\Domain\Model\AnalysisResult;
use Koh\ContentIntelligence\Domain\Model\ContentSnapshot;

interface AnalyzerInterface
{
    public function getIdentifier(): string;

    public function getName(): string;

    public function analyze(ContentSnapshot $content): AnalysisResult;

    /**
     * Weight of this analyzer in the overall score (0.0 - 1.0).
     */
    public function getWeight(): float;
}
