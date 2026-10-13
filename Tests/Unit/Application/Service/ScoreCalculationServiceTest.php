<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Tests\Unit\Application\Service;

use Koh\ContentIntelligence\Application\Service\ScoreCalculationService;
use Koh\ContentIntelligence\Domain\Model\AnalysisResult;
use Koh\ContentIntelligence\Domain\Model\ScoreThreshold;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScoreCalculationServiceTest extends TestCase
{
    private ScoreCalculationService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new ScoreCalculationService();
    }

    #[Test]
    public function calculateWithMultipleResultsAndWeightsReturnsCorrectWeightedScore(): void
    {
        $results = [
            new AnalysisResult(
                analyzerIdentifier: 'metadata',
                score: 80.0,
                details: [],
                suggestions: [],
            ),
            new AnalysisResult(
                analyzerIdentifier: 'structure',
                score: 60.0,
                details: [],
                suggestions: [],
            ),
        ];

        $weights = [
            'metadata' => 0.7,
            'structure' => 0.3,
        ];

        $score = $this->subject->calculate(1, 0, $results, $weights);

        // (80*0.7 + 60*0.3) / (0.7+0.3) = (56+18)/1.0 = 74.0
        self::assertSame(74.0, $score->overallScore);
        self::assertSame(1, $score->pageUid);
        self::assertSame(0, $score->languageUid);
        self::assertSame(['metadata' => 80.0, 'structure' => 60.0], $score->analyzerScores);
    }

    #[Test]
    public function calculateWithNoResultsReturnsZeroScore(): void
    {
        $score = $this->subject->calculate(5, 1, [], []);

        self::assertSame(0.0, $score->overallScore);
        self::assertSame(5, $score->pageUid);
        self::assertSame(1, $score->languageUid);
        self::assertSame([], $score->analyzerScores);
        self::assertSame(ScoreThreshold::Critical, $score->threshold);
    }

    #[Test]
    public function calculateWithEqualWeightsReturnsArithmeticMean(): void
    {
        $results = [
            new AnalysisResult(
                analyzerIdentifier: 'analyzer_a',
                score: 90.0,
                details: [],
                suggestions: [],
            ),
            new AnalysisResult(
                analyzerIdentifier: 'analyzer_b',
                score: 70.0,
                details: [],
                suggestions: [],
            ),
            new AnalysisResult(
                analyzerIdentifier: 'analyzer_c',
                score: 50.0,
                details: [],
                suggestions: [],
            ),
        ];

        $weights = [
            'analyzer_a' => 1.0,
            'analyzer_b' => 1.0,
            'analyzer_c' => 1.0,
        ];

        $score = $this->subject->calculate(1, 0, $results, $weights);

        // (90+70+50)/3 = 70.0
        self::assertSame(70.0, $score->overallScore);
    }

    #[Test]
    public function thresholdIsCorrectlyAssignedBasedOnScore(): void
    {
        // Critical: score < 50
        $resultsCritical = [
            new AnalysisResult(analyzerIdentifier: 'a', score: 30.0, details: [], suggestions: []),
        ];
        $scoreCritical = $this->subject->calculate(1, 0, $resultsCritical, ['a' => 1.0]);
        self::assertSame(ScoreThreshold::Critical, $scoreCritical->threshold);

        // NeedsImprovement: 50 <= score < 80
        $resultsNeeds = [
            new AnalysisResult(analyzerIdentifier: 'a', score: 65.0, details: [], suggestions: []),
        ];
        $scoreNeeds = $this->subject->calculate(1, 0, $resultsNeeds, ['a' => 1.0]);
        self::assertSame(ScoreThreshold::NeedsImprovement, $scoreNeeds->threshold);

        // AiReady: score >= 80
        $resultsReady = [
            new AnalysisResult(analyzerIdentifier: 'a', score: 85.0, details: [], suggestions: []),
        ];
        $scoreReady = $this->subject->calculate(1, 0, $resultsReady, ['a' => 1.0]);
        self::assertSame(ScoreThreshold::AiReady, $scoreReady->threshold);
    }

    #[Test]
    public function scoreIsRoundedToTwoDecimalPlaces(): void
    {
        $results = [
            new AnalysisResult(analyzerIdentifier: 'a', score: 33.33, details: [], suggestions: []),
            new AnalysisResult(analyzerIdentifier: 'b', score: 66.67, details: [], suggestions: []),
            new AnalysisResult(analyzerIdentifier: 'c', score: 100.0, details: [], suggestions: []),
        ];

        $weights = [
            'a' => 1.0,
            'b' => 1.0,
            'c' => 1.0,
        ];

        $score = $this->subject->calculate(1, 0, $results, $weights);

        // (33.33+66.67+100)/3 = 200/3 = 66.666... -> 66.67
        self::assertSame(66.67, $score->overallScore);
    }
}
