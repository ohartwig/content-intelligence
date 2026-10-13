<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Application\Service;

use Koh\ContentIntelligence\Domain\Model\AnalysisResult;
use Koh\ContentIntelligence\Domain\Model\ReadinessScore;
use Koh\ContentIntelligence\Domain\Model\ScoreThreshold;

final readonly class ScoreCalculationService
{
    /**
     * @param AnalysisResult[]     $results
     * @param array<string, float> $weights Analyzer identifier => weight mapping
     */
    public function calculate(
        int $pageUid,
        int $languageUid,
        array $results,
        array $weights,
    ): ReadinessScore {
        $analyzerScores = [];
        $weightedSum = 0.0;
        $totalWeight = 0.0;

        foreach ($results as $result) {
            $analyzerScores[$result->analyzerIdentifier] = $result->score;
            $weight = $weights[$result->analyzerIdentifier] ?? 0.0;
            $weightedSum += $result->score * $weight;
            $totalWeight += $weight;
        }

        $overallScore = $totalWeight > 0.0 ? $weightedSum / $totalWeight : 0.0;
        $overallScore = round($overallScore, 2);

        return new ReadinessScore(
            pageUid: $pageUid,
            languageUid: $languageUid,
            overallScore: $overallScore,
            analyzerScores: $analyzerScores,
            threshold: ScoreThreshold::fromScore($overallScore),
            analyzedAt: new \DateTimeImmutable(),
        );
    }
}
