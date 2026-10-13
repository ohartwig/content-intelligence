<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Tests\Unit\Infrastructure\DataHandler;

use Doctrine\DBAL\Result;
use Koh\ContentIntelligence\Application\Service\AnalysisOrchestrator;
use Koh\ContentIntelligence\Application\Service\ScoreCalculationService;
use Koh\ContentIntelligence\Domain\Contract\AnalysisResultRepositoryInterface;
use Koh\ContentIntelligence\Domain\Contract\ContentExtractorInterface;
use Koh\ContentIntelligence\Domain\Model\ContentSnapshot;
use Koh\ContentIntelligence\Infrastructure\DataHandler\ContentChangeHook;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * The hook decides which pages get re-analyzed after an editor saves. Getting
 * that set wrong is not visible anywhere: too few pages and the readiness
 * scores quietly go stale, too many and every save drags unrelated analyses
 * along.
 *
 * The orchestrator is final, so it is built for real with no analyzers — it
 * then does nothing observable, which is all these tests need from it.
 */
final class ContentChangeHookTest extends TestCase
{
    private ContentExtractorInterface&MockObject $extractor;
    private LoggerInterface&MockObject $logger;
    private ConnectionPool&MockObject $connectionPool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extractor = $this->createMock(ContentExtractorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->connectionPool = $this->createMock(ConnectionPool::class);
    }

    private function hook(): ContentChangeHook
    {
        $orchestrator = new AnalysisOrchestrator(
            [],
            $this->createMock(AnalysisResultRepositoryInterface::class),
            new ScoreCalculationService(),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(LoggerInterface::class),
        );

        return new ContentChangeHook($this->extractor, $orchestrator, $this->connectionPool, $this->logger);
    }

    /**
     * @param array<string, array<int|string, array<string, mixed>>> $datamap
     * @param array<string, int>                                     $substNEWwithIDs
     */
    private function dataHandler(array $datamap, array $substNEWwithIDs = []): DataHandler
    {
        $dataHandler = $this->createMock(DataHandler::class);
        $dataHandler->datamap = $datamap;
        $dataHandler->substNEWwithIDs = $substNEWwithIDs;

        return $dataHandler;
    }

    private function snapshot(int $pageUid = 1): ContentSnapshot
    {
        // ContentSnapshot is final; a real one is cheap and carries no behaviour.
        return new ContentSnapshot(
            pageUid: $pageUid,
            languageUid: 0,
            title: 'title',
            description: 'description',
            bodyTexts: [],
            headings: [],
            metaTags: [],
            schemaOrgData: [],
            lastModified: new \DateTimeImmutable('2026-07-29 12:00:00'),
        );
    }

    #[Test]
    public function editedPagesAreAnalyzed(): void
    {
        $this->extractor->expects(self::once())
            ->method('extractForPage')
            ->with(42)
            ->willReturn($this->snapshot());

        $this->hook()->processDatamap_afterAllOperations(
            $this->dataHandler(['pages' => [42 => ['title' => 'changed']]]),
        );
    }

    /**
     * A newly created page reaches the hook with a placeholder id. Without the
     * NEW-substitution it would be cast to 0 and silently skipped, so brand new
     * pages would never get an initial score.
     */
    #[Test]
    public function newPagePlaceholdersAreResolvedToTheirRealUid(): void
    {
        $this->extractor->expects(self::once())
            ->method('extractForPage')
            ->with(77)
            ->willReturn($this->snapshot());

        $this->hook()->processDatamap_afterAllOperations(
            $this->dataHandler(
                ['pages' => ['NEW123abc' => ['title' => 'fresh']]],
                ['NEW123abc' => 77],
            ),
        );
    }

    /**
     * An unresolvable placeholder must not turn into page 0.
     */
    #[Test]
    public function unresolvedPlaceholdersAreDropped(): void
    {
        $this->extractor->expects(self::never())->method('extractForPage');

        $this->hook()->processDatamap_afterAllOperations(
            $this->dataHandler(['pages' => ['NEWnope' => ['title' => 'fresh']]]),
        );
    }

    /**
     * Content elements carry their page in the pid, which spares the lookup.
     */
    #[Test]
    public function contentElementsAreAnalyzedViaTheirPid(): void
    {
        $this->connectionPool->expects(self::never())->method('getQueryBuilderForTable');
        $this->extractor->expects(self::once())
            ->method('extractForPage')
            ->with(5)
            ->willReturn($this->snapshot());

        $this->hook()->processDatamap_afterAllOperations(
            $this->dataHandler(['tt_content' => [9 => ['pid' => 5, 'header' => 'changed']]]),
        );
    }

    /**
     * On an update DataHandler often omits the pid, because it did not change.
     * The page then has to come from the database, otherwise editing existing
     * content would never trigger a re-analysis.
     */
    #[Test]
    public function contentElementsWithoutPidAreLookedUp(): void
    {
        $this->connectionPool->method('getQueryBuilderForTable')
            ->with('tt_content')
            ->willReturn($this->queryBuilderReturning(11));

        $this->extractor->expects(self::once())
            ->method('extractForPage')
            ->with(11)
            ->willReturn($this->snapshot());

        $this->hook()->processDatamap_afterAllOperations(
            $this->dataHandler(['tt_content' => [9 => ['header' => 'changed']]]),
        );
    }

    /**
     * A lookup that finds nothing must not be analyzed as page 0.
     */
    #[Test]
    public function contentElementsWithoutAResolvablePageAreDropped(): void
    {
        $this->connectionPool->method('getQueryBuilderForTable')
            ->willReturn($this->queryBuilderReturning(false));

        $this->extractor->expects(self::never())->method('extractForPage');

        $this->hook()->processDatamap_afterAllOperations(
            $this->dataHandler(['tt_content' => [9 => ['header' => 'changed']]]),
        );
    }

    /**
     * Several elements on one page must collapse into a single analysis, and
     * tables the hook does not care about are ignored entirely.
     */
    #[Test]
    public function eachPageIsAnalyzedOnceAndUnrelatedTablesAreIgnored(): void
    {
        $this->extractor->expects(self::once())
            ->method('extractForPage')
            ->with(3)
            ->willReturn($this->snapshot());

        $this->hook()->processDatamap_afterAllOperations(
            $this->dataHandler([
                'tt_content' => [
                    1 => ['pid' => 3],
                    2 => ['pid' => 3],
                ],
                'sys_category' => [8 => ['title' => 'irrelevant']],
            ]),
        );
    }

    /**
     * Analysis runs inside a save operation, so a failure must be logged rather
     * than propagated — an editor should not get an exception because a score
     * could not be recalculated. Later pages still have to be processed.
     */
    #[Test]
    public function aFailingPageIsLoggedAndDoesNotStopTheRest(): void
    {
        $this->extractor->method('extractForPage')
            ->willReturnCallback(function (int $pageUid): ContentSnapshot {
                if (1 === $pageUid) {
                    throw new \RuntimeException('extraction broke');
                }

                return $this->snapshot();
            });

        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                'Content analysis after change failed',
                self::callback(static fn(array $ctx): bool => 1 === $ctx['pageUid'] && 'extraction broke' === $ctx['error']),
            );

        $this->hook()->processDatamap_afterAllOperations(
            $this->dataHandler(['pages' => [1 => ['title' => 'a'], 2 => ['title' => 'b']]]),
        );
    }

    #[Test]
    public function anEmptyDatamapDoesNothing(): void
    {
        $this->extractor->expects(self::never())->method('extractForPage');

        $this->hook()->processDatamap_afterAllOperations($this->dataHandler([]));
    }

    private function queryBuilderReturning(int|false $pid): QueryBuilder&MockObject
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchOne')->willReturn($pid);

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
