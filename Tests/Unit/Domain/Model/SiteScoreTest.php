<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Tests\Unit\Domain\Model;

use Koh\ContentIntelligence\Domain\Model\SiteScore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SiteScoreTest extends TestCase
{
    #[Test]
    public function canBeConstructedWithAllProperties(): void
    {
        $siteScore = new SiteScore(
            rootPageUid: 1,
            averageScore: 67.3,
            pageCount: 50,
            criticalCount: 5,
            needsImprovementCount: 20,
            aiReadyCount: 25,
        );

        self::assertSame(1, $siteScore->rootPageUid);
        self::assertSame(67.3, $siteScore->averageScore);
        self::assertSame(50, $siteScore->pageCount);
        self::assertSame(5, $siteScore->criticalCount);
        self::assertSame(20, $siteScore->needsImprovementCount);
        self::assertSame(25, $siteScore->aiReadyCount);
    }

    #[Test]
    public function canBeConstructedWithZeroCounts(): void
    {
        $siteScore = new SiteScore(
            rootPageUid: 10,
            averageScore: 0.0,
            pageCount: 0,
            criticalCount: 0,
            needsImprovementCount: 0,
            aiReadyCount: 0,
        );

        self::assertSame(0, $siteScore->pageCount);
        self::assertSame(0.0, $siteScore->averageScore);
    }
}
