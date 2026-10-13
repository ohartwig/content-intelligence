<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Domain\Contract;

use Koh\ContentIntelligence\Domain\Model\AuditEntry;

interface AuditEntryRepositoryInterface
{
    public function save(AuditEntry $entry): void;

    /**
     * @return AuditEntry[]
     */
    public function findByPage(int $pageUid): array;

    /**
     * @return AuditEntry[]
     */
    public function findUnreviewed(int $limit = 50): array;
}
