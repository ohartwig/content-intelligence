<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Infrastructure\Persistence;

use Koh\ContentIntelligence\Domain\Contract\AuditEntryRepositoryInterface;
use Koh\ContentIntelligence\Domain\Model\AuditEntry;
use Koh\ContentIntelligence\Domain\Model\ContentOrigin;
use TYPO3\CMS\Core\Database\ConnectionPool;

final readonly class DoctrineAuditEntryRepository implements AuditEntryRepositoryInterface
{
    private const TABLE = 'tx_contentintelligence_audit_entry';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function save(AuditEntry $entry): void
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $connection->insert(self::TABLE, [
            'pid' => $entry->pageUid,
            'page_uid' => $entry->pageUid,
            'content_uid' => $entry->contentUid,
            'table_name' => $entry->tableName,
            'field_name' => $entry->fieldName,
            'origin' => $entry->origin->value,
            'ai_provider' => $entry->aiProvider,
            'ai_model' => $entry->aiModel,
            'reviewed_by' => $entry->reviewedBy,
            'reviewed_at' => $entry->reviewedAt?->getTimestamp() ?? 0,
            'created_at' => $entry->createdAt->getTimestamp(),
        ]);
    }

    /**
     * @return AuditEntry[]
     */
    public function findByPage(int $pageUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $rows = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('page_uid', $queryBuilder->createNamedParameter($pageUid, \Doctrine\DBAL\ParameterType::INTEGER)))
            ->orderBy('created_at', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map($this->hydrateEntry(...), $rows);
    }

    /**
     * @return AuditEntry[]
     */
    public function findUnreviewed(int $limit = 50): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $rows = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('reviewed_by', $queryBuilder->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER)))
            ->orderBy('created_at', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map($this->hydrateEntry(...), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateEntry(array $row): AuditEntry
    {
        $reviewedAt = (int) ($row['reviewed_at'] ?? 0);

        return new AuditEntry(
            pageUid: (int) $row['page_uid'],
            contentUid: (int) $row['content_uid'],
            tableName: (string) $row['table_name'],
            fieldName: (string) $row['field_name'],
            origin: ContentOrigin::from((string) $row['origin']),
            aiProvider: (string) $row['ai_provider'],
            aiModel: (string) $row['ai_model'],
            reviewedBy: (int) ($row['reviewed_by'] ?? 0),
            reviewedAt: $reviewedAt > 0 ? new \DateTimeImmutable('@' . $reviewedAt) : null,
            createdAt: new \DateTimeImmutable('@' . (int) $row['created_at']),
        );
    }
}
