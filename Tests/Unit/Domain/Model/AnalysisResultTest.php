<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Tests\Unit\Domain\Model;

use Koh\ContentIntelligence\Domain\Model\AnalysisResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AnalysisResultTest extends TestCase
{
    #[Test]
    public function canBeConstructedWithAllProperties(): void
    {
        $details = ['key' => 'value', 'count' => 42];
        $suggestions = ['Add meta description', 'Improve heading structure'];

        $result = new AnalysisResult(
            analyzerIdentifier: 'metadata_completeness',
            score: 75.5,
            details: $details,
            suggestions: $suggestions,
        );

        self::assertSame('metadata_completeness', $result->analyzerIdentifier);
        self::assertSame(75.5, $result->score);
        self::assertSame($details, $result->details);
        self::assertSame($suggestions, $result->suggestions);
    }

    #[Test]
    public function canBeConstructedWithEmptyArrays(): void
    {
        $result = new AnalysisResult(
            analyzerIdentifier: 'structure_quality',
            score: 100.0,
            details: [],
            suggestions: [],
        );

        self::assertSame('structure_quality', $result->analyzerIdentifier);
        self::assertSame(100.0, $result->score);
        self::assertSame([], $result->details);
        self::assertSame([], $result->suggestions);
    }

    #[Test]
    public function scoreCanBeZero(): void
    {
        $result = new AnalysisResult(
            analyzerIdentifier: 'freshness',
            score: 0.0,
            details: [],
            suggestions: ['Content is outdated'],
        );

        self::assertSame(0.0, $result->score);
    }
}
