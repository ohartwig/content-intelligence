<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Infrastructure\Persistence;

use Doctrine\DBAL\ParameterType;
use Koh\ContentIntelligence\Domain\Contract\ContentExtractorInterface;
use Koh\ContentIntelligence\Domain\Model\ContentSnapshot;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;

final readonly class ContentExtractorService implements ContentExtractorInterface
{
    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function extractForPage(int $pageUid, int $languageUid = 0): ContentSnapshot
    {
        $pageData = $this->fetchPageData($pageUid, $languageUid);
        $contentElements = $this->fetchContentElements($pageUid, $languageUid);

        $bodyTexts = [];
        $headings = [];

        foreach ($contentElements as $element) {
            $bodyText = trim((string) ($element['bodytext'] ?? ''));
            if ('' !== $bodyText) {
                $bodyTexts[] = strip_tags($bodyText);
            }

            $header = trim((string) ($element['header'] ?? ''));
            if ('' !== $header) {
                $headings[] = $header;
            }

            $subheader = trim((string) ($element['subheader'] ?? ''));
            if ('' !== $subheader) {
                $headings[] = $subheader;
            }
        }

        $metaTags = $this->extractMetaTags($pageData);
        $schemaOrgData = $this->extractSchemaOrgData($pageData);

        $lastModified = new \DateTimeImmutable('@' . ($pageData['tstamp'] ?? time()));

        return new ContentSnapshot(
            pageUid: $pageUid,
            languageUid: $languageUid,
            title: (string) ($pageData['title'] ?? ''),
            description: (string) ($pageData['description'] ?? ''),
            bodyTexts: $bodyTexts,
            headings: $headings,
            metaTags: $metaTags,
            schemaOrgData: $schemaOrgData,
            lastModified: $lastModified,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchPageData(int $pageUid, int $languageUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);

        if ($languageUid > 0) {
            $result = $queryBuilder
                ->select('*')
                ->from('pages')
                ->where(
                    $queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($pageUid, ParameterType::INTEGER)),
                    $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageUid, ParameterType::INTEGER)),
                )
                ->executeQuery()
                ->fetchAssociative();

            if (is_array($result)) {
                return $result;
            }
        }

        $result = $queryBuilder
            ->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageUid, ParameterType::INTEGER)),
            )
            ->executeQuery()
            ->fetchAssociative();

        return is_array($result) ? $result : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchContentElements(int $pageUid, int $languageUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');

        $result = $queryBuilder
            ->select('*')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageUid, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageUid, ParameterType::INTEGER)),
            )
            ->orderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();

        return $result;
    }

    /**
     * @param array<string, mixed> $pageData
     *
     * @return array<string, string>
     */
    private function extractMetaTags(array $pageData): array
    {
        $metaTags = [];

        if (isset($pageData['title']) && '' !== (string) $pageData['title']) {
            $metaTags['title'] = (string) $pageData['title'];
        }
        if (isset($pageData['description']) && '' !== (string) $pageData['description']) {
            $metaTags['description'] = (string) $pageData['description'];
        }
        if (isset($pageData['og_title']) && '' !== (string) $pageData['og_title']) {
            $metaTags['og:title'] = (string) $pageData['og_title'];
        }
        if (isset($pageData['og_description']) && '' !== (string) $pageData['og_description']) {
            $metaTags['og:description'] = (string) $pageData['og_description'];
        }
        if (isset($pageData['og_image']) && '' !== (string) $pageData['og_image']) {
            $metaTags['og:image'] = (string) $pageData['og_image'];
        }
        if (isset($pageData['twitter_title']) && '' !== (string) $pageData['twitter_title']) {
            $metaTags['twitter:title'] = (string) $pageData['twitter_title'];
        }
        if (isset($pageData['twitter_description']) && '' !== (string) $pageData['twitter_description']) {
            $metaTags['twitter:description'] = (string) $pageData['twitter_description'];
        }
        if (isset($pageData['canonical_link']) && '' !== (string) $pageData['canonical_link']) {
            $metaTags['canonical'] = (string) $pageData['canonical_link'];
        }

        return $metaTags;
    }

    /**
     * @param array<string, mixed> $pageData
     *
     * @return array<string, mixed>
     */
    private function extractSchemaOrgData(array $pageData): array
    {
        // TYPO3 does not store schema.org data natively in pages table
        // This can be extended via events or custom fields
        return [];
    }
}
