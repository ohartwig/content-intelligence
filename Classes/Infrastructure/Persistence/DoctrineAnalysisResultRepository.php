<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Infrastructure\Persistence;

use Koh\ContentIntelligence\Domain\Contract\AnalysisResultRepositoryInterface;
use Koh\ContentIntelligence\Domain\Model\AnalysisResult;
use TYPO3\CMS\Core\Database\ConnectionPool;

final readonly class DoctrineAnalysisResultRepository implements AnalysisResultRepositoryInterface
{
    private const TABLE = 'tx_contentintelligence_analysis_result';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * @param AnalysisResult[] $results
     */
    public function saveForPage(int $pageUid, int $languageUid, array $results): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);

        // Delete existing results for this page/language
        $this->deleteByPage($pageUid, $languageUid);

        foreach ($results as $result) {
            $connection->insert(self::TABLE, [
                'pid' => $pageUid,
                'page_uid' => $pageUid,
                'language_uid' => $languageUid,
                'analyzer_identifier' => $result->analyzerIdentifier,
                'score' => $result->score,
                'details' => json_encode($result->details, JSON_THROW_ON_ERROR),
                'suggestions' => json_encode($result->suggestions, JSON_THROW_ON_ERROR),
                'analyzed_at' => time(),
            ]);
        }

        // Update pages table
        $pagesConnection = $this->connectionPool->getConnectionForTable('pages');
        $pagesConnection->update('pages', [
            'tx_contentintelligence_last_analysis' => time(),
        ], ['uid' => $pageUid]);
    }

    /**
     * @return AnalysisResult[]
     */
    public function findByPage(int $pageUid, int $languageUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $rows = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('page_uid', $queryBuilder->createNamedParameter($pageUid, \Doctrine\DBAL\ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('language_uid', $queryBuilder->createNamedParameter($languageUid, \Doctrine\DBAL\ParameterType::INTEGER)),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(
            static fn(array $row): AnalysisResult => new AnalysisResult(
                analyzerIdentifier: (string) $row['analyzer_identifier'],
                score: (float) $row['score'],
                details: json_decode((string) $row['details'], true) ?? [],
                suggestions: json_decode((string) $row['suggestions'], true) ?? [],
            ),
            $rows,
        );
    }

    public function deleteByPage(int $pageUid, int $languageUid): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $connection->delete(self::TABLE, [
            'page_uid' => $pageUid,
            'language_uid' => $languageUid,
        ]);
    }
}
