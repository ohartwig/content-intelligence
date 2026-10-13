<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Infrastructure\Scheduler;

use Koh\ContentIntelligence\Application\Service\AnalysisOrchestrator;
use Koh\ContentIntelligence\Infrastructure\Persistence\ContentExtractorService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

final class ContentAnalysisTask extends AbstractTask
{
    private ?ContentExtractorService $contentExtractor = null;
    private ?AnalysisOrchestrator $orchestrator = null;
    private ?ConnectionPool $connectionPool = null;
    protected ?LoggerInterface $logger = null;

    public function execute(): bool
    {
        $this->initializeDependencies();

        if (null === $this->connectionPool || null === $this->contentExtractor || null === $this->orchestrator) {
            return false;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $pages = $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('doktype', $queryBuilder->createNamedParameter(1, \Doctrine\DBAL\ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER)),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $success = true;
        foreach ($pages as $page) {
            try {
                $snapshot = $this->contentExtractor->extractForPage((int) $page['uid']);
                $this->orchestrator->analyze($snapshot);
            } catch (\Throwable $e) {
                $this->logger?->error('Scheduled analysis failed', [
                    'pageUid' => $page['uid'],
                    'error' => $e->getMessage(),
                ]);
                $success = false;
            }
        }

        return $success;
    }

    private function initializeDependencies(): void
    {
        $container = \TYPO3\CMS\Core\Utility\GeneralUtility::getContainer();
        $this->contentExtractor = $container->get(ContentExtractorService::class);
        $this->orchestrator = $container->get(AnalysisOrchestrator::class);
        $this->connectionPool = $container->get(ConnectionPool::class);
        $this->logger = $container->get(LoggerInterface::class);
    }
}
