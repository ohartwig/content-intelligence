<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Domain\Model;

final readonly class AuditEntry
{
    public function __construct(
        public int $pageUid,
        public int $contentUid,
        public string $tableName,
        public string $fieldName,
        public ContentOrigin $origin,
        public string $aiProvider,
        public string $aiModel,
        public int $reviewedBy,
        public ?\DateTimeImmutable $reviewedAt,
        public \DateTimeImmutable $createdAt,
    ) {}
}
