<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Tests\Unit\Application\Service;

use Koh\ContentIntelligence\Infrastructure\Persistence\ContentExtractorService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionContainerInterface;

final class ContentExtractorServiceTest extends TestCase
{
    private ConnectionPool&MockObject $connectionPool;
    private ContentExtractorService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connectionPool = $this->createMock(ConnectionPool::class);
        $this->subject = new ContentExtractorService($this->connectionPool);
    }

    private function createQueryBuilderMock(): QueryBuilder&MockObject
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $restrictions = $this->createMock(QueryRestrictionContainerInterface::class);

        $queryBuilder->method('getRestrictions')->willReturn($restrictions);
        $restrictions->method('removeByType')->willReturnSelf();

        $expressionBuilder->method('eq')->willReturn('');
        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('createNamedParameter')->willReturn('');
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();

        return $queryBuilder;
    }

    private function createResultMock(array|false $fetchAssociativeReturn, array $fetchAllAssociativeReturn = []): \Doctrine\DBAL\Result&MockObject
    {
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $result->method('fetchAssociative')->willReturn($fetchAssociativeReturn);
        $result->method('fetchAllAssociative')->willReturn($fetchAllAssociativeReturn);

        return $result;
    }

    #[Test]
    public function extractForPageBuildsContentSnapshotFromPageAndContentData(): void
    {
        $pageData = [
            'uid' => 1,
            'title' => 'My Page',
            'description' => 'Page description',
            'og_title' => 'OG Title',
            'og_description' => 'OG Description',
            'tstamp' => 1700000000,
        ];

        $contentElements = [
            [
                'uid' => 10,
                'bodytext' => '<p>First paragraph</p>',
                'header' => 'Section One',
                'subheader' => 'Sub Section',
            ],
            [
                'uid' => 11,
                'bodytext' => '<strong>Bold text</strong>',
                'header' => 'Section Two',
                'subheader' => '',
            ],
            [
                'uid' => 12,
                'bodytext' => '',
                'header' => '',
                'subheader' => '',
            ],
        ];

        $pageQueryBuilder = $this->createQueryBuilderMock();
        $pageResult = $this->createResultMock($pageData);
        $pageQueryBuilder->method('executeQuery')->willReturn($pageResult);

        $contentQueryBuilder = $this->createQueryBuilderMock();
        $contentResult = $this->createResultMock(false, $contentElements);
        $contentQueryBuilder->method('executeQuery')->willReturn($contentResult);

        $callCount = 0;
        $this->connectionPool
            ->method('getQueryBuilderForTable')
            ->willReturnCallback(function (string $table) use (&$callCount, $pageQueryBuilder, $contentQueryBuilder) {
                ++$callCount;
                if ('pages' === $table) {
                    return $pageQueryBuilder;
                }

                return $contentQueryBuilder;
            });

        $snapshot = $this->subject->extractForPage(1, 0);

        self::assertSame(1, $snapshot->pageUid);
        self::assertSame(0, $snapshot->languageUid);
        self::assertSame('My Page', $snapshot->title);
        self::assertSame('Page description', $snapshot->description);

        // bodyTexts: tags stripped, empty skipped
        self::assertCount(2, $snapshot->bodyTexts);
        self::assertSame('First paragraph', $snapshot->bodyTexts[0]);
        self::assertSame('Bold text', $snapshot->bodyTexts[1]);

        // headings: empty skipped, subheaders included
        self::assertSame(['Section One', 'Sub Section', 'Section Two'], $snapshot->headings);

        // metaTags extracted from page data
        self::assertSame('My Page', $snapshot->metaTags['title']);
        self::assertSame('Page description', $snapshot->metaTags['description']);
        self::assertSame('OG Title', $snapshot->metaTags['og:title']);
        self::assertSame('OG Description', $snapshot->metaTags['og:description']);

        // schemaOrgData is always empty (default implementation)
        self::assertSame([], $snapshot->schemaOrgData);

        // lastModified from tstamp
        self::assertSame(1700000000, $snapshot->lastModified->getTimestamp());
    }

    /**
     * The social and canonical fields feed the metadata analyzer, which scores
     * a page on how much of them is filled in. TYPO3 stores the untouched ones
     * as empty strings, so copying them over unconditionally would present a
     * page as fully tagged while every tag is blank — and a field that is not
     * copied at all costs the page points it has earned.
     */
    #[Test]
    public function onlyPopulatedSocialAndCanonicalFieldsBecomeMetaTags(): void
    {
        $pageData = [
            'uid' => 1,
            'title' => 'My Page',
            'description' => 'Page description',
            'og_title' => '',
            'og_description' => '',
            'og_image' => 'fileadmin/social/og.jpg',
            'twitter_title' => 'Twitter Title',
            'twitter_description' => 'Twitter Description',
            'canonical_link' => 'https://example.org/my-page',
            'tstamp' => 1700000000,
        ];

        $pageQueryBuilder = $this->createQueryBuilderMock();
        $pageQueryBuilder->method('executeQuery')->willReturn($this->createResultMock($pageData));

        $contentQueryBuilder = $this->createQueryBuilderMock();
        $contentQueryBuilder->method('executeQuery')->willReturn($this->createResultMock(false, []));

        $this->connectionPool
            ->method('getQueryBuilderForTable')
            ->willReturnCallback(fn(string $table): QueryBuilder => 'pages' === $table ? $pageQueryBuilder : $contentQueryBuilder);

        $snapshot = $this->subject->extractForPage(1, 0);

        self::assertSame('fileadmin/social/og.jpg', $snapshot->metaTags['og:image']);
        self::assertSame('Twitter Title', $snapshot->metaTags['twitter:title']);
        self::assertSame('Twitter Description', $snapshot->metaTags['twitter:description']);
        self::assertSame('https://example.org/my-page', $snapshot->metaTags['canonical']);

        self::assertArrayNotHasKey('og:title', $snapshot->metaTags, 'an empty field must not be reported as a filled-in tag');
        self::assertArrayNotHasKey('og:description', $snapshot->metaTags);
    }

    #[Test]
    public function extractForPageWithEmptyPageDataReturnsSnapshotWithDefaults(): void
    {
        $pageQueryBuilder = $this->createQueryBuilderMock();
        $pageResult = $this->createResultMock(false);
        $pageQueryBuilder->method('executeQuery')->willReturn($pageResult);

        $contentQueryBuilder = $this->createQueryBuilderMock();
        $contentResult = $this->createResultMock(false, []);
        $contentQueryBuilder->method('executeQuery')->willReturn($contentResult);

        $this->connectionPool
            ->method('getQueryBuilderForTable')
            ->willReturnCallback(function (string $table) use ($pageQueryBuilder, $contentQueryBuilder) {
                return 'pages' === $table ? $pageQueryBuilder : $contentQueryBuilder;
            });

        $snapshot = $this->subject->extractForPage(99, 0);

        self::assertSame(99, $snapshot->pageUid);
        self::assertSame('', $snapshot->title);
        self::assertSame('', $snapshot->description);
        self::assertSame([], $snapshot->bodyTexts);
        self::assertSame([], $snapshot->headings);
        self::assertSame([], $snapshot->metaTags);
    }
}
