<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Tests\Unit\Infrastructure\Persistence;

use Doctrine\DBAL\Result;
use Koh\ContentIntelligence\Domain\Model\AnalysisResult;
use Koh\ContentIntelligence\Infrastructure\Persistence\DoctrineAnalysisResultRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * What matters in this repository is the translation between the domain object
 * and the row: the JSON columns, the numeric casts, and the fact that saving is
 * a replace rather than an append. A silent mismatch here surfaces as scores
 * that look plausible but belong to an earlier analysis.
 */
final class DoctrineAnalysisResultRepositoryTest extends TestCase
{
    private const TABLE = 'tx_contentintelligence_analysis_result';

    private ConnectionPool&MockObject $connectionPool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connectionPool = $this->createMock(ConnectionPool::class);
    }

    /**
     * Results are written per page and language, with the JSON columns encoded
     * and the page's analysis timestamp refreshed. The delete has to come first
     * — otherwise a re-analysis would stack onto the previous run.
     */
    #[Test]
    public function savingReplacesPreviousResultsAndStampsThePage(): void
    {
        $calls = [];

        $resultConnection = $this->createMock(Connection::class);
        $resultConnection->method('delete')
            ->willReturnCallback(static function (string $table, array $criteria) use (&$calls): int {
                $calls[] = ['delete', $table, $criteria];

                return 1;
            });
        $resultConnection->method('insert')
            ->willReturnCallback(static function (string $table, array $row) use (&$calls): int {
                $calls[] = ['insert', $table, $row];

                return 1;
            });

        $pagesConnection = $this->createMock(Connection::class);
        $pagesConnection->method('update')
            ->willReturnCallback(static function (string $table, array $data, array $criteria) use (&$calls): int {
                $calls[] = ['update', $table, $criteria];

                return 1;
            });

        $this->connectionPool->method('getConnectionForTable')
            ->willReturnCallback(fn(string $table): Connection => 'pages' === $table ? $pagesConnection : $resultConnection);

        new DoctrineAnalysisResultRepository($this->connectionPool)->saveForPage(12, 1, [
            new AnalysisResult(
                analyzerIdentifier: 'freshness',
                score: 82.5,
                details: ['age' => 3],
                suggestions: ['refresh the intro'],
            ),
        ]);

        self::assertSame('delete', $calls[0][0], 'previous results must be removed before new ones are written');
        self::assertSame(['page_uid' => 12, 'language_uid' => 1], $calls[0][2]);

        self::assertSame('insert', $calls[1][0]);
        self::assertSame(self::TABLE, $calls[1][1]);
        $row = $calls[1][2];
        self::assertSame(12, $row['page_uid']);
        self::assertSame(1, $row['language_uid']);
        self::assertSame('freshness', $row['analyzer_identifier']);
        self::assertSame(82.5, $row['score']);
        self::assertSame('{"age":3}', $row['details']);
        self::assertSame('["refresh the intro"]', $row['suggestions']);
        self::assertIsInt($row['analyzed_at']);

        self::assertSame('update', $calls[2][0]);
        self::assertSame('pages', $calls[2][1]);
        self::assertSame(['uid' => 12], $calls[2][2]);
    }

    /**
     * An analysis that produced nothing still has to clear the old results,
     * otherwise stale scores would survive a run that found none.
     */
    #[Test]
    public function savingAnEmptyResultSetStillClearsThePreviousOnes(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('delete')->willReturn(1);
        $connection->expects(self::never())->method('insert');
        $connection->method('update')->willReturn(1);

        $this->connectionPool->method('getConnectionForTable')->willReturn($connection);

        new DoctrineAnalysisResultRepository($this->connectionPool)->saveForPage(12, 0, []);
    }

    #[Test]
    public function rowsAreMappedBackIntoAnalysisResults(): void
    {
        $this->connectionPool->method('getQueryBuilderForTable')
            ->with(self::TABLE)
            ->willReturn($this->queryBuilderReturning([
                [
                    'analyzer_identifier' => 'structure',
                    'score' => '64.25',
                    'details' => '{"headings":2}',
                    'suggestions' => '["add an h2"]',
                ],
            ]));

        $results = new DoctrineAnalysisResultRepository($this->connectionPool)->findByPage(12, 0);

        self::assertCount(1, $results);
        self::assertSame('structure', $results[0]->analyzerIdentifier);
        self::assertSame(64.25, $results[0]->score, 'the score column is numeric and must not stay a string');
        self::assertSame(['headings' => 2], $results[0]->details);
        self::assertSame(['add an h2'], $results[0]->suggestions);
    }

    /**
     * The JSON columns are written by this same class, but a row can predate a
     * format change or have been touched by hand. Undecodable JSON has to
     * degrade to an empty list instead of producing null and breaking every
     * consumer that iterates it.
     */
    #[Test]
    public function unreadableJsonColumnsFallBackToEmptyLists(): void
    {
        $this->connectionPool->method('getQueryBuilderForTable')
            ->willReturn($this->queryBuilderReturning([
                [
                    'analyzer_identifier' => 'structure',
                    'score' => '10',
                    'details' => 'not json',
                    'suggestions' => '',
                ],
            ]));

        $results = new DoctrineAnalysisResultRepository($this->connectionPool)->findByPage(12, 0);

        self::assertSame([], $results[0]->details);
        self::assertSame([], $results[0]->suggestions);
    }

    #[Test]
    public function noRowsYieldNoResults(): void
    {
        $this->connectionPool->method('getQueryBuilderForTable')
            ->willReturn($this->queryBuilderReturning([]));

        self::assertSame([], new DoctrineAnalysisResultRepository($this->connectionPool)->findByPage(12, 0));
    }

    #[Test]
    public function deletingIsScopedToPageAndLanguage(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('delete')
            ->with(self::TABLE, ['page_uid' => 7, 'language_uid' => 2])
            ->willReturn(1);

        $this->connectionPool->method('getConnectionForTable')->with(self::TABLE)->willReturn($connection);

        new DoctrineAnalysisResultRepository($this->connectionPool)->deleteByPage(7, 2);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function queryBuilderReturning(array $rows): QueryBuilder&MockObject
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn($rows);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('createNamedParameter')->willReturn(':dcValue1');
        $queryBuilder->method('expr')->willReturn($this->createMock(ExpressionBuilder::class));
        $queryBuilder->method('executeQuery')->willReturn($result);

        return $queryBuilder;
    }
}
