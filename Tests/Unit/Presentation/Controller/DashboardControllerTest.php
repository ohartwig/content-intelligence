<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Tests\Unit\Presentation\Controller;

use Koh\ContentIntelligence\Presentation\Controller\DashboardController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The dashboard summary is the number an editor acts on: it decides how many
 * pages are flagged as critical and what the site average looks like. It is
 * derived from raw database rows, so the arithmetic is exercised directly here.
 *
 * The surrounding action cannot be driven from a unit test — it renders through
 * ModuleTemplate, which is final and has to be built by the framework — so the
 * calculation is invoked on an instance created without its constructor. That
 * is safe precisely because the method reads nothing but its argument.
 */
final class DashboardControllerTest extends TestCase
{
    /**
     * The score arrives as a database string and a page that was queued but
     * never scored has no score column at all. Without the cast and the
     * fallback, ScoreThreshold::fromScore() would compare against a string and
     * bucket the pages wrongly — an unscored page in particular has to count as
     * critical rather than blow up the summary.
     */
    #[Test]
    public function pagesAreBucketedByThresholdAndAveragedAcrossTheSite(): void
    {
        $summary = $this->summaryFor([
            ['uid' => 1, 'tx_contentintelligence_readiness_score' => '20.0'],
            ['uid' => 2, 'tx_contentintelligence_readiness_score' => '65.5'],
            ['uid' => 3, 'tx_contentintelligence_readiness_score' => 92],
            ['uid' => 4],
        ]);

        self::assertSame(4, $summary['total']);
        self::assertSame(2, $summary['critical'], 'the unscored page counts as critical, not as a fourth bucket');
        self::assertSame(1, $summary['needsImprovement']);
        self::assertSame(1, $summary['aiReady']);
        self::assertSame(44.38, $summary['averageScore']);
    }

    /**
     * A site where nothing has been analysed yet reaches the same code path.
     * The average is a division by the page count, so the empty case has to be
     * short-circuited instead of producing a division by zero.
     */
    #[Test]
    public function aSiteWithoutAnalysedPagesReportsAZeroAverage(): void
    {
        $summary = $this->summaryFor([]);

        self::assertSame(0, $summary['total']);
        self::assertSame(0.0, $summary['averageScore']);
    }

    /**
     * @param array<int, array<string, mixed>> $pages
     *
     * @return array{total: int, critical: int, needsImprovement: int, aiReady: int, averageScore: float}
     */
    private function summaryFor(array $pages): array
    {
        $controller = new \ReflectionClass(DashboardController::class)->newInstanceWithoutConstructor();

        /** @var array{total: int, critical: int, needsImprovement: int, aiReady: int, averageScore: float} $summary */
        $summary = new \ReflectionMethod(DashboardController::class, 'calculateSummary')->invoke($controller, $pages);

        return $summary;
    }
}
