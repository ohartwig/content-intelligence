<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Tests\Unit\Presentation\ViewHelpers;

use Koh\ContentIntelligence\Presentation\ViewHelpers\ReadinessScoreBadgeViewHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * The badge is the only place where a readiness score turns into something an
 * editor sees, so the mapping from score to colour and wording is what matters
 * here — a score that lands in the wrong band is misleading rather than merely
 * ugly.
 *
 * renderStatic() is called directly: it ignores both the closure and the
 * rendering context, and going through render() would only add Fluid setup
 * without exercising anything else.
 */
final class ReadinessScoreBadgeViewHelperTest extends TestCase
{
    /**
     * @return array<string, array{float, string, string}>
     */
    public static function scoreProvider(): array
    {
        return [
            'zero is critical' => [0.0, 'bg-danger', 'Critical'],
            'just below the critical boundary' => [49.9, 'bg-danger', 'Critical'],
            'the boundary itself needs improvement' => [50.0, 'bg-warning text-dark', 'Needs Improvement'],
            'just below the ai-ready boundary' => [79.9, 'bg-warning text-dark', 'Needs Improvement'],
            'the boundary itself is ai-ready' => [80.0, 'bg-success', 'AI-Ready'],
            'full score is ai-ready' => [100.0, 'bg-success', 'AI-Ready'],
        ];
    }

    #[Test]
    #[DataProvider('scoreProvider')]
    public function scoreMapsToTheMatchingBadge(float $score, string $expectedClass, string $expectedLabel): void
    {
        $output = ReadinessScoreBadgeViewHelper::renderStatic(
            ['score' => $score],
            static fn(): string => '',
            self::createStub(RenderingContextInterface::class),
        );

        self::assertStringContainsString('class="badge ' . $expectedClass . '"', $output);
        self::assertStringContainsString($expectedLabel, $output);
    }

    /**
     * The score is formatted without decimals, so a fractional value must not
     * leak into the badge.
     */
    #[Test]
    public function scoreIsRenderedAsAWholePercentage(): void
    {
        $output = ReadinessScoreBadgeViewHelper::renderStatic(
            ['score' => 87.6],
            static fn(): string => '',
            self::createStub(RenderingContextInterface::class),
        );

        self::assertSame('<span class="badge bg-success">AI-Ready (88%)</span>', $output);
    }

    /**
     * The argument arrives as a Fluid variable and may well be a string. The
     * cast has to hold, otherwise a numeric string would be compared as one and
     * land in the wrong band.
     */
    #[Test]
    public function numericStringsAreCastBeforeTheThresholdIsPicked(): void
    {
        $output = ReadinessScoreBadgeViewHelper::renderStatic(
            ['score' => '42'],
            static fn(): string => '',
            self::createStub(RenderingContextInterface::class),
        );

        self::assertStringContainsString('bg-danger', $output);
        self::assertStringContainsString('(42%)', $output);
    }
}
