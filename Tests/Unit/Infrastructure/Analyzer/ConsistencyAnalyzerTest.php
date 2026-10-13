<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Tests\Unit\Infrastructure\Analyzer;

use Koh\AiWorkflows\Domain\Contract\AiClientInterface;
use Koh\ContentIntelligence\Domain\Contract\ContentExtractorInterface;
use Koh\ContentIntelligence\Domain\Model\ContentSnapshot;
use Koh\ContentIntelligence\Infrastructure\Analyzer\ConsistencyAnalyzer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

final class ConsistencyAnalyzerTest extends TestCase
{
    private AiClientInterface&MockObject $aiClient;
    private ContentExtractorInterface&MockObject $contentExtractor;
    private ConnectionPool&MockObject $connectionPool;
    private ConsistencyAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aiClient = $this->createMock(AiClientInterface::class);
        $this->contentExtractor = $this->createMock(ContentExtractorInterface::class);
        $this->connectionPool = $this->createMock(ConnectionPool::class);
        $this->analyzer = new ConsistencyAnalyzer(
            $this->aiClient,
            $this->contentExtractor,
            $this->connectionPool,
        );
    }

    #[Test]
    public function returnsCorrectIdentifier(): void
    {
        self::assertSame('consistency', $this->analyzer->getIdentifier());
    }

    #[Test]
    public function returnsCorrectName(): void
    {
        self::assertSame('Content Consistency', $this->analyzer->getName());
    }

    #[Test]
    public function returnsWeight015(): void
    {
        self::assertSame(0.15, $this->analyzer->getWeight());
    }

    #[Test]
    public function emptyContentReturnsScore50(): void
    {
        $snapshot = $this->createSnapshot([
            'bodyTexts' => [],
        ]);

        $result = $this->analyzer->analyze($snapshot);

        self::assertSame(50.0, $result->score);
        self::assertSame('No content to analyze', $result->details['error']);
        self::assertContains('Add text content to enable consistency analysis.', $result->suggestions);
    }

    #[Test]
    public function noSiblingsReturnsScore100(): void
    {
        $snapshot = $this->createSnapshot([
            'bodyTexts' => ['Some valid content to analyze.'],
            'pageUid' => 42,
        ]);

        $this->mockQueryBuilderForNoSiblings();

        $result = $this->analyzer->analyze($snapshot);

        self::assertSame(100.0, $result->score);
        self::assertSame([], $result->suggestions);
    }

    /**
     * This analyzer has no rule-based fallback: the score it reports is the one
     * the model returned. If the response were not read back, every page with
     * siblings would keep the optimistic default of 100 and contradictions
     * found across pages would never reach the editor. Entries without an
     * `issue` are the model's own noise and must not become suggestions.
     */
    #[Test]
    public function theAiVerdictBecomesTheScoreAndItsContradictionsBecomeSuggestions(): void
    {
        $snapshot = $this->createSnapshot([
            'bodyTexts' => ['Our delivery time is three days.'],
            'pageUid' => 42,
        ]);

        $this->mockQueryBuilderWithSiblings(42, [['uid' => 43]]);

        $this->contentExtractor
            ->method('extractForPage')
            ->willReturn($this->createSnapshot([
                'pageUid' => 43,
                'title' => 'Sibling Page',
                'bodyTexts' => ['Our delivery time is five days.'],
            ]));

        $this->aiClient
            ->method('generate')
            ->willReturn(json_encode([
                'consistencyScore' => 72.5,
                'contradictions' => [
                    ['mainPageText' => 'three days', 'relatedPageText' => 'five days', 'issue' => 'The delivery time differs between the pages.'],
                    ['mainPageText' => 'three days'],
                    'not a contradiction object',
                ],
            ], JSON_THROW_ON_ERROR));

        $result = $this->analyzer->analyze($snapshot);

        self::assertSame(72.5, $result->score);
        self::assertSame(['The delivery time differs between the pages.'], $result->suggestions);
        self::assertArrayHasKey('aiAnalysis', $result->details);
        self::assertSame(1, $result->details['siblingCount']);
    }

    #[Test]
    public function handlesAiClientFailureGracefully(): void
    {
        $snapshot = $this->createSnapshot([
            'bodyTexts' => ['Some valid content to analyze.'],
            'pageUid' => 42,
        ]);

        $this->mockQueryBuilderWithSiblings(42, [['uid' => 43]]);

        $siblingSnapshot = $this->createSnapshot([
            'pageUid' => 43,
            'title' => 'Sibling Page',
            'bodyTexts' => ['Sibling content here.'],
        ]);

        $this->contentExtractor
            ->method('extractForPage')
            ->willReturn($siblingSnapshot);

        $this->aiClient
            ->method('generate')
            ->willThrowException(new \RuntimeException('AI service unavailable'));

        $result = $this->analyzer->analyze($snapshot);

        self::assertSame(50.0, $result->score);
        self::assertArrayHasKey('aiError', $result->details);
        self::assertSame('AI service unavailable', $result->details['aiError']);
    }

    private function createSnapshot(array $overrides = []): ContentSnapshot
    {
        return new ContentSnapshot(
            pageUid: $overrides['pageUid'] ?? 1,
            languageUid: $overrides['languageUid'] ?? 0,
            title: $overrides['title'] ?? 'Test Page',
            description: $overrides['description'] ?? 'Test description for the page that is long enough',
            bodyTexts: $overrides['bodyTexts'] ?? ['Some body text content.'],
            headings: $overrides['headings'] ?? ['Heading 1', 'Heading 2'],
            metaTags: $overrides['metaTags'] ?? [],
            schemaOrgData: $overrides['schemaOrgData'] ?? [],
            lastModified: $overrides['lastModified'] ?? new \DateTimeImmutable(),
        );
    }

    /**
     * Mocks the QueryBuilder chain for the case where the parent page lookup
     * returns pid=0, meaning no siblings can be found.
     */
    private function mockQueryBuilderForNoSiblings(): void
    {
        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $expressionBuilder->method('eq')->willReturn('1=1');
        $expressionBuilder->method('neq')->willReturn('1=0');

        $statement = $this->createMock(\Doctrine\DBAL\Result::class);
        $statement->method('fetchAssociative')->willReturn(['pid' => 0]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('createNamedParameter')->willReturn('1');
        $queryBuilder->method('executeQuery')->willReturn($statement);

        $this->connectionPool
            ->method('getQueryBuilderForTable')
            ->willReturn($queryBuilder);
    }

    /**
     * Mocks the QueryBuilder chain for the case where siblings exist.
     *
     * @param int                         $pageUid  The current page UID
     * @param array<int, array{uid: int}> $siblings The sibling page rows
     */
    private function mockQueryBuilderWithSiblings(int $pageUid, array $siblings): void
    {
        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $expressionBuilder->method('eq')->willReturn('1=1');
        $expressionBuilder->method('neq')->willReturn('1=0');

        // First call: get parent PID
        $parentStatement = $this->createMock(\Doctrine\DBAL\Result::class);
        $parentStatement->method('fetchAssociative')->willReturn(['pid' => 10]);

        // Second call: get siblings
        $siblingsStatement = $this->createMock(\Doctrine\DBAL\Result::class);
        $siblingsStatement->method('fetchAllAssociative')->willReturn($siblings);

        $queryBuilder1 = $this->createMock(QueryBuilder::class);
        $queryBuilder1->method('select')->willReturnSelf();
        $queryBuilder1->method('from')->willReturnSelf();
        $queryBuilder1->method('where')->willReturnSelf();
        $queryBuilder1->method('expr')->willReturn($expressionBuilder);
        $queryBuilder1->method('createNamedParameter')->willReturn('1');
        $queryBuilder1->method('executeQuery')->willReturn($parentStatement);

        $queryBuilder2 = $this->createMock(QueryBuilder::class);
        $queryBuilder2->method('select')->willReturnSelf();
        $queryBuilder2->method('from')->willReturnSelf();
        $queryBuilder2->method('where')->willReturnSelf();
        $queryBuilder2->method('setMaxResults')->willReturnSelf();
        $queryBuilder2->method('expr')->willReturn($expressionBuilder);
        $queryBuilder2->method('createNamedParameter')->willReturn('1');
        $queryBuilder2->method('executeQuery')->willReturn($siblingsStatement);

        $this->connectionPool
            ->method('getQueryBuilderForTable')
            ->willReturnOnConsecutiveCalls($queryBuilder1, $queryBuilder2);
    }
}
