<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Infrastructure\Analyzer;

use Doctrine\DBAL\ParameterType;
use Koh\AiWorkflows\Domain\Contract\AiClientInterface;
use Koh\ContentIntelligence\Domain\Contract\ContentExtractorInterface;
use Koh\ContentIntelligence\Domain\Contract\AnalyzerInterface;
use Koh\ContentIntelligence\Domain\Model\AnalysisResult;
use Koh\ContentIntelligence\Domain\Model\ContentSnapshot;
use TYPO3\CMS\Core\Database\ConnectionPool;

final readonly class ConsistencyAnalyzer implements AnalyzerInterface
{
    public function __construct(
        private AiClientInterface $aiClient,
        private ContentExtractorInterface $contentExtractor,
        private ConnectionPool $connectionPool,
    ) {}

    public function getIdentifier(): string
    {
        return 'consistency';
    }

    public function getName(): string
    {
        return 'Content Consistency';
    }

    public function getWeight(): float
    {
        return 0.15;
    }

    public function analyze(ContentSnapshot $content): AnalysisResult
    {
        $details = [];
        $suggestions = [];

        $combinedText = implode("\n", $content->bodyTexts);
        if ('' === $combinedText) {
            return new AnalysisResult(
                analyzerIdentifier: $this->getIdentifier(),
                score: 50.0,
                details: ['error' => 'No content to analyze'],
                suggestions: ['Add text content to enable consistency analysis.'],
            );
        }

        // Find sibling pages (same parent)
        $siblingSnapshots = $this->getSiblingSnapshots($content->pageUid, $content->languageUid);
        $details['siblingCount'] = count($siblingSnapshots);

        if ([] === $siblingSnapshots) {
            return new AnalysisResult(
                analyzerIdentifier: $this->getIdentifier(),
                score: 100.0,
                details: $details,
                suggestions: [],
            );
        }

        // Build context from siblings
        $siblingContext = '';
        foreach ($siblingSnapshots as $snapshot) {
            $siblingText = implode(' ', $snapshot->bodyTexts);
            if ('' !== $siblingText) {
                $siblingContext .= sprintf("--- Page: %s ---\n%s\n\n", $snapshot->title, mb_substr($siblingText, 0, 500));
            }
        }

        $truncatedText = mb_substr($combinedText, 0, 2000);

        $systemPrompt = 'You are a content consistency analyzer. Compare content across pages for contradictions and inconsistencies. Always respond with valid JSON only.';
        $userPrompt = "Compare the main content with related pages for consistency. Look for:\n1. Contradictory information (different facts, numbers, dates)\n2. Inconsistent terminology\n3. Conflicting claims or statements\n\nMain page (\"" . $content->title . "\"):\n" . $truncatedText . "\n\nRelated pages:\n" . mb_substr($siblingContext, 0, 3000) . "\n\nRespond in JSON format: {\"consistencyScore\": 0-100, \"contradictions\": [{\"mainPageText\": \"...\", \"relatedPageText\": \"...\", \"issue\": \"...\"}]}";
        $prompt = $systemPrompt . "\n\n" . $userPrompt;

        $score = 100.0;

        try {
            $response = $this->aiClient->generate($prompt, ['temperature' => 0.1, 'max_tokens' => 1500]);
            $aiResult = json_decode($response, true);

            if (is_array($aiResult)) {
                $score = (float) ($aiResult['consistencyScore'] ?? 100);
                $details['aiAnalysis'] = $aiResult;

                if (isset($aiResult['contradictions']) && is_array($aiResult['contradictions'])) {
                    foreach ($aiResult['contradictions'] as $contradiction) {
                        if (is_array($contradiction) && isset($contradiction['issue'])) {
                            $suggestions[] = (string) $contradiction['issue'];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $details['aiError'] = $e->getMessage();
            $score = 50.0;
        }

        return new AnalysisResult(
            analyzerIdentifier: $this->getIdentifier(),
            score: max(0.0, min(100.0, round($score, 2))),
            details: $details,
            suggestions: $suggestions,
        );
    }

    /**
     * @return ContentSnapshot[]
     */
    private function getSiblingSnapshots(int $pageUid, int $languageUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');

        // Get parent page UID
        $parentResult = $queryBuilder
            ->select('pid')
            ->from('pages')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageUid, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchAssociative();

        if (!is_array($parentResult) || 0 === (int) $parentResult['pid']) {
            return [];
        }

        $parentUid = (int) $parentResult['pid'];

        // Get sibling pages (max 5)
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $siblings = $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($parentUid, ParameterType::INTEGER)),
                $queryBuilder->expr()->neq('uid', $queryBuilder->createNamedParameter($pageUid, ParameterType::INTEGER)),
            )
            ->setMaxResults(5)
            ->executeQuery()
            ->fetchAllAssociative();

        $snapshots = [];
        foreach ($siblings as $sibling) {
            try {
                $snapshots[] = $this->contentExtractor->extractForPage((int) $sibling['uid'], $languageUid);
            } catch (\Throwable) {
                // Skip pages that fail extraction
            }
        }

        return $snapshots;
    }
}
