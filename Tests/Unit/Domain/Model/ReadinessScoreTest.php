<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Tests\Unit\Domain\Model;

use Koh\ContentIntelligence\Domain\Model\ReadinessScore;
use Koh\ContentIntelligence\Domain\Model\ScoreThreshold;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReadinessScoreTest extends TestCase
{
    #[Test]
    public function canBeConstructedWithAllProperties(): void
    {
        $analyzerScores = [
            'metadata_completeness' => 85.0,
            'structure_quality' => 72.5,
        ];
        $analyzedAt = new \DateTimeImmutable('2026-03-23 10:00:00');

        $score = new ReadinessScore(
            pageUid: 42,
            languageUid: 0,
            overallScore: 78.75,
            analyzerScores: $analyzerScores,
            threshold: ScoreThreshold::NeedsImprovement,
            analyzedAt: $analyzedAt,
        );

        self::assertSame(42, $score->pageUid);
        self::assertSame(0, $score->languageUid);
        self::assertSame(78.75, $score->overallScore);
        self::assertSame($analyzerScores, $score->analyzerScores);
        self::assertSame(ScoreThreshold::NeedsImprovement, $score->threshold);
        self::assertSame($analyzedAt, $score->analyzedAt);
    }

    #[Test]
    public function thresholdMatchesScoreCategory(): void
    {
        $score = new ReadinessScore(
            pageUid: 1,
            languageUid: 0,
            overallScore: 90.0,
            analyzerScores: [],
            threshold: ScoreThreshold::AiReady,
            analyzedAt: new \DateTimeImmutable(),
        );

        self::assertSame(ScoreThreshold::AiReady, $score->threshold);
    }

    #[Test]
    public function canBeConstructedWithEmptyAnalyzerScores(): void
    {
        $score = new ReadinessScore(
            pageUid: 1,
            languageUid: 1,
            overallScore: 0.0,
            analyzerScores: [],
            threshold: ScoreThreshold::Critical,
            analyzedAt: new \DateTimeImmutable(),
        );

        self::assertSame([], $score->analyzerScores);
        self::assertSame(ScoreThreshold::Critical, $score->threshold);
    }
}
