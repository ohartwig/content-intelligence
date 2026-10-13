<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Infrastructure\DataHandler;

use Doctrine\DBAL\ParameterType;
use Koh\ContentIntelligence\Application\Service\AnalysisOrchestrator;
use Koh\ContentIntelligence\Domain\Contract\ContentExtractorInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;

final readonly class ContentChangeHook
{
    public function __construct(
        private ContentExtractorInterface $contentExtractor,
        private AnalysisOrchestrator $orchestrator,
        private ConnectionPool $connectionPool,
        private LoggerInterface $logger,
    ) {}

    /**
     * Hook called after all database operations of DataHandler.
     */
    public function processDatamap_afterAllOperations(DataHandler $dataHandler): void
    {
        $affectedPageUids = $this->collectAffectedPageUids($dataHandler);

        foreach ($affectedPageUids as $pageUid) {
            try {
                $snapshot = $this->contentExtractor->extractForPage($pageUid);
                $this->orchestrator->analyze($snapshot);
            } catch (\Throwable $e) {
                $this->logger->error('Content analysis after change failed', [
                    'pageUid' => $pageUid,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return int[]
     */
    private function collectAffectedPageUids(DataHandler $dataHandler): array
    {
        $pageUids = [];

        foreach ($dataHandler->datamap as $table => $records) {
            if ('pages' === $table) {
                foreach (array_keys($records) as $uid) {
                    $resolvedUid = $this->resolveUid($uid, $table, $dataHandler);
                    if ($resolvedUid > 0) {
                        $pageUids[$resolvedUid] = true;
                    }
                }
            } elseif ('tt_content' === $table) {
                foreach ($records as $uid => $data) {
                    $pid = (int) ($data['pid'] ?? 0);
                    if ($pid > 0) {
                        $pageUids[$pid] = true;
                    } else {
                        $resolvedUid = $this->resolveUid($uid, $table, $dataHandler);
                        if ($resolvedUid > 0) {
                            $pid = $this->lookupContentPid($resolvedUid);
                            if ($pid > 0) {
                                $pageUids[$pid] = true;
                            }
                        }
                    }
                }
            }
        }

        return array_keys($pageUids);
    }

    private function lookupContentPid(int $contentUid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $pid = $queryBuilder
            ->select('pid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($contentUid, ParameterType::INTEGER)),
            )
            ->executeQuery()
            ->fetchOne();

        return is_int($pid) || is_string($pid) ? (int) $pid : 0;
    }

    private function resolveUid(int|string $uid, string $table, DataHandler $dataHandler): int
    {
        if (is_string($uid) && str_starts_with($uid, 'NEW')) {
            $uid = $dataHandler->substNEWwithIDs[$uid] ?? 0;
        }

        return (int) $uid;
    }
}
