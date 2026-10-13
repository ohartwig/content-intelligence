<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Domain\Model;

final readonly class ContentSnapshot
{
    /**
     * @param string[]              $bodyTexts
     * @param string[]              $headings
     * @param array<string, string> $metaTags
     * @param array<string, mixed>  $schemaOrgData
     */
    public function __construct(
        public int $pageUid,
        public int $languageUid,
        public string $title,
        public string $description,
        public array $bodyTexts,
        public array $headings,
        public array $metaTags,
        public array $schemaOrgData,
        public \DateTimeImmutable $lastModified,
    ) {}
}
