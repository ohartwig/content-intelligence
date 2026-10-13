<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Infrastructure\Analyzer;

use Koh\ContentIntelligence\Domain\Contract\AnalyzerInterface;
use Koh\ContentIntelligence\Domain\Model\AnalysisResult;
use Koh\ContentIntelligence\Domain\Model\ContentSnapshot;

final readonly class StructureQualityAnalyzer implements AnalyzerInterface
{
    private const MIN_BODY_TEXT_LENGTH = 100;
    private const IDEAL_HEADING_COUNT_MIN = 2;
    private const IDEAL_HEADING_COUNT_MAX = 10;

    public function getIdentifier(): string
    {
        return 'structure_quality';
    }

    public function getName(): string
    {
        return 'Structure Quality';
    }

    public function getWeight(): float
    {
        return 0.25;
    }

    public function analyze(ContentSnapshot $content): AnalysisResult
    {
        $score = 0.0;
        $details = [];
        $suggestions = [];

        // 1. Content presence check (30 points)
        $totalTextLength = 0;
        foreach ($content->bodyTexts as $text) {
            $totalTextLength += mb_strlen($text);
        }
        $details['totalTextLength'] = $totalTextLength;
        $details['contentBlockCount'] = count($content->bodyTexts);

        if ($totalTextLength >= self::MIN_BODY_TEXT_LENGTH) {
            $score += 30.0;
        } elseif ($totalTextLength > 0) {
            $ratio = $totalTextLength / self::MIN_BODY_TEXT_LENGTH;
            $score += 30.0 * $ratio;
            $suggestions[] = sprintf('Content is short (%d characters). Aim for at least %d characters.', $totalTextLength, self::MIN_BODY_TEXT_LENGTH);
        } else {
            $suggestions[] = 'Page has no text content.';
        }

        // 2. Heading structure (30 points)
        $headingCount = count($content->headings);
        $details['headingCount'] = $headingCount;

        if ($headingCount >= self::IDEAL_HEADING_COUNT_MIN && $headingCount <= self::IDEAL_HEADING_COUNT_MAX) {
            $score += 30.0;
        } elseif ($headingCount > 0) {
            if ($headingCount < self::IDEAL_HEADING_COUNT_MIN) {
                $score += 15.0;
                $suggestions[] = sprintf('Only %d heading(s) found. Use more headings to structure your content.', $headingCount);
            } else {
                $score += 20.0;
                $suggestions[] = sprintf('Too many headings (%d). Consider consolidating content sections.', $headingCount);
            }
        } else {
            $suggestions[] = 'No headings found. Use headings (H1-H6) to structure your content.';
        }

        // 3. Content block variety (20 points)
        $blockCount = count($content->bodyTexts);
        if ($blockCount >= 3) {
            $score += 20.0;
        } elseif ($blockCount >= 1) {
            $score += 10.0;
            $suggestions[] = 'Content uses few content blocks. Consider breaking content into multiple sections.';
        } else {
            $suggestions[] = 'No content blocks found.';
        }

        // 4. Title and description present (20 points)
        if ('' !== $content->title) {
            $score += 10.0;
            $details['hasTitle'] = true;
        } else {
            $details['hasTitle'] = false;
            $suggestions[] = 'Page title is missing.';
        }

        if ('' !== $content->description) {
            $score += 10.0;
            $details['hasDescription'] = true;
        } else {
            $details['hasDescription'] = false;
            $suggestions[] = 'Page description is missing.';
        }

        $normalizedScore = min(100.0, $score);

        return new AnalysisResult(
            analyzerIdentifier: $this->getIdentifier(),
            score: round($normalizedScore, 2),
            details: $details,
            suggestions: $suggestions,
        );
    }
}
