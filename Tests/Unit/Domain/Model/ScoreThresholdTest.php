<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Tests\Unit\Domain\Model;

use Koh\ContentIntelligence\Domain\Model\ScoreThreshold;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScoreThresholdTest extends TestCase
{
    #[Test]
    public function criticalCaseHasCorrectValue(): void
    {
        self::assertSame('critical', ScoreThreshold::Critical->value);
    }

    #[Test]
    public function needsImprovementCaseHasCorrectValue(): void
    {
        self::assertSame('needs_improvement', ScoreThreshold::NeedsImprovement->value);
    }

    #[Test]
    public function aiReadyCaseHasCorrectValue(): void
    {
        self::assertSame('ai_ready', ScoreThreshold::AiReady->value);
    }

    #[Test]
    public function allCasesArePresent(): void
    {
        $cases = ScoreThreshold::cases();
        self::assertCount(3, $cases);
    }

    #[Test]
    public function fromScoreReturnsCorrectThresholdForZero(): void
    {
        self::assertSame(ScoreThreshold::Critical, ScoreThreshold::fromScore(0.0));
    }

    #[Test]
    public function fromScoreReturnsCorrectThresholdForJustBelowFifty(): void
    {
        self::assertSame(ScoreThreshold::Critical, ScoreThreshold::fromScore(49.99));
    }

    #[Test]
    public function fromScoreReturnsCorrectThresholdForExactlyFifty(): void
    {
        self::assertSame(ScoreThreshold::NeedsImprovement, ScoreThreshold::fromScore(50.0));
    }

    #[Test]
    public function fromScoreReturnsCorrectThresholdForJustBelowEighty(): void
    {
        self::assertSame(ScoreThreshold::NeedsImprovement, ScoreThreshold::fromScore(79.99));
    }

    #[Test]
    public function fromScoreReturnsCorrectThresholdForExactlyEighty(): void
    {
        self::assertSame(ScoreThreshold::AiReady, ScoreThreshold::fromScore(80.0));
    }

    #[Test]
    public function fromScoreReturnsCorrectThresholdForHundred(): void
    {
        self::assertSame(ScoreThreshold::AiReady, ScoreThreshold::fromScore(100.0));
    }
}
