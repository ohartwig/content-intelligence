<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Application\Service;

use Koh\ContentIntelligence\Application\Event\AuditEntryCreatedEvent;
use Koh\ContentIntelligence\Domain\Contract\AuditEntryRepositoryInterface;
use Koh\ContentIntelligence\Domain\Model\AuditEntry;
use Koh\ContentIntelligence\Domain\Model\ContentOrigin;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class AuditService
{
    public function __construct(
        private AuditEntryRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function track(
        int $pageUid,
        int $contentUid,
        string $tableName,
        string $fieldName,
        ContentOrigin $origin,
        string $aiProvider = '',
        string $aiModel = '',
    ): AuditEntry {
        $entry = new AuditEntry(
            pageUid: $pageUid,
            contentUid: $contentUid,
            tableName: $tableName,
            fieldName: $fieldName,
            origin: $origin,
            aiProvider: $aiProvider,
            aiModel: $aiModel,
            reviewedBy: 0,
            reviewedAt: null,
            createdAt: new \DateTimeImmutable(),
        );

        $this->repository->save($entry);

        $this->eventDispatcher->dispatch(new AuditEntryCreatedEvent($entry));

        return $entry;
    }

    /**
     * @return AuditEntry[]
     */
    public function getPageAuditLog(int $pageUid): array
    {
        return $this->repository->findByPage($pageUid);
    }

    /**
     * @return AuditEntry[]
     */
    public function getUnreviewedEntries(int $limit = 50): array
    {
        return $this->repository->findUnreviewed($limit);
    }
}
