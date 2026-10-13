<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Presentation\Controller;

use Koh\ContentIntelligence\Application\Service\AuditService;
use Koh\ContentIntelligence\Domain\Contract\AnalysisResultRepositoryInterface;
use Koh\ContentIntelligence\Domain\Model\ScoreThreshold;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class DashboardController extends ActionController
{
    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly AnalysisResultRepositoryInterface $resultRepository,
        private readonly AuditService $auditService,
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function indexAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);

        $pages = $this->getAnalyzedPages();
        $summary = $this->calculateSummary($pages);

        $moduleTemplate->assignMultiple([
            'pages' => $pages,
            'summary' => $summary,
        ]);

        return $moduleTemplate->renderResponse('Dashboard/Index');
    }

    public function pageDetailAction(int $pageUid = 0, int $languageUid = 0): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);

        $results = $this->resultRepository->findByPage($pageUid, $languageUid);

        $moduleTemplate->assignMultiple([
            'pageUid' => $pageUid,
            'languageUid' => $languageUid,
            'results' => $results,
        ]);

        return $moduleTemplate->renderResponse('Dashboard/PageDetail');
    }

    public function auditLogAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);

        $entries = $this->auditService->getUnreviewedEntries(100);

        $moduleTemplate->assignMultiple([
            'entries' => $entries,
        ]);

        return $moduleTemplate->renderResponse('Dashboard/AuditLog');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getAnalyzedPages(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');

        return $queryBuilder
            ->select('uid', 'title', 'tx_contentintelligence_readiness_score', 'tx_contentintelligence_last_analysis')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->gt('tx_contentintelligence_last_analysis', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER)),
            )
            ->orderBy('tx_contentintelligence_readiness_score', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param array<int, array<string, mixed>> $pages
     *
     * @return array{total: int, critical: int, needsImprovement: int, aiReady: int, averageScore: float}
     */
    private function calculateSummary(array $pages): array
    {
        $total = count($pages);
        $critical = 0;
        $needsImprovement = 0;
        $aiReady = 0;
        $scoreSum = 0.0;

        foreach ($pages as $page) {
            $score = (float) ($page['tx_contentintelligence_readiness_score'] ?? 0);
            $scoreSum += $score;
            $threshold = ScoreThreshold::fromScore($score);

            match ($threshold) {
                ScoreThreshold::Critical => $critical++,
                ScoreThreshold::NeedsImprovement => $needsImprovement++,
                ScoreThreshold::AiReady => $aiReady++,
            };
        }

        return [
            'total' => $total,
            'critical' => $critical,
            'needsImprovement' => $needsImprovement,
            'aiReady' => $aiReady,
            'averageScore' => $total > 0 ? round($scoreSum / $total, 2) : 0.0,
        ];
    }
}
