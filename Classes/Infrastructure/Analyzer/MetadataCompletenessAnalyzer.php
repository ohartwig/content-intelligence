<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Infrastructure\Analyzer;

use Koh\ContentIntelligence\Domain\Contract\AnalyzerInterface;
use Koh\ContentIntelligence\Domain\Model\AnalysisResult;
use Koh\ContentIntelligence\Domain\Model\ContentSnapshot;

final readonly class MetadataCompletenessAnalyzer implements AnalyzerInterface
{
    private const FIELD_WEIGHTS = [
        'title' => 15.0,
        'description' => 15.0,
        'og:title' => 10.0,
        'og:description' => 10.0,
        'og:image' => 10.0,
        'twitter:title' => 7.5,
        'twitter:description' => 7.5,
        'canonical' => 10.0,
        'schema.org' => 10.0,
    ];

    // Description length thresholds
    private const MIN_DESCRIPTION_LENGTH = 50;
    private const MAX_DESCRIPTION_LENGTH = 160;
    private const MIN_TITLE_LENGTH = 10;
    private const MAX_TITLE_LENGTH = 70;

    public function getIdentifier(): string
    {
        return 'metadata_completeness';
    }

    public function getName(): string
    {
        return 'Metadata Completeness';
    }

    public function getWeight(): float
    {
        return 0.25;
    }

    public function analyze(ContentSnapshot $content): AnalysisResult
    {
        $score = 0.0;
        $maxScore = array_sum(self::FIELD_WEIGHTS);
        $details = [];
        $suggestions = [];

        // Title check
        if ('' !== $content->title) {
            $titleLength = mb_strlen($content->title);
            $details['title'] = ['present' => true, 'length' => $titleLength];
            if ($titleLength >= self::MIN_TITLE_LENGTH && $titleLength <= self::MAX_TITLE_LENGTH) {
                $score += self::FIELD_WEIGHTS['title'];
            } else {
                $score += self::FIELD_WEIGHTS['title'] * 0.5;
                if ($titleLength < self::MIN_TITLE_LENGTH) {
                    $suggestions[] = sprintf('Title is too short (%d chars). Aim for %d-%d characters.', $titleLength, self::MIN_TITLE_LENGTH, self::MAX_TITLE_LENGTH);
                } else {
                    $suggestions[] = sprintf('Title is too long (%d chars). Aim for %d-%d characters.', $titleLength, self::MIN_TITLE_LENGTH, self::MAX_TITLE_LENGTH);
                }
            }
        } else {
            $details['title'] = ['present' => false];
            $suggestions[] = 'Page title is missing.';
        }

        // Description check
        if ('' !== $content->description) {
            $descLength = mb_strlen($content->description);
            $details['description'] = ['present' => true, 'length' => $descLength];
            if ($descLength >= self::MIN_DESCRIPTION_LENGTH && $descLength <= self::MAX_DESCRIPTION_LENGTH) {
                $score += self::FIELD_WEIGHTS['description'];
            } else {
                $score += self::FIELD_WEIGHTS['description'] * 0.5;
                if ($descLength < self::MIN_DESCRIPTION_LENGTH) {
                    $suggestions[] = sprintf('Meta description is too short (%d chars). Aim for %d-%d characters.', $descLength, self::MIN_DESCRIPTION_LENGTH, self::MAX_DESCRIPTION_LENGTH);
                } else {
                    $suggestions[] = sprintf('Meta description is too long (%d chars). Aim for %d-%d characters.', $descLength, self::MIN_DESCRIPTION_LENGTH, self::MAX_DESCRIPTION_LENGTH);
                }
            }
        } else {
            $details['description'] = ['present' => false];
            $suggestions[] = 'Meta description is missing.';
        }

        // OpenGraph checks
        foreach (['og:title', 'og:description', 'og:image'] as $ogField) {
            if (isset($content->metaTags[$ogField]) && '' !== $content->metaTags[$ogField]) {
                $score += self::FIELD_WEIGHTS[$ogField];
                $details[$ogField] = ['present' => true];
            } else {
                $details[$ogField] = ['present' => false];
                $suggestions[] = sprintf('OpenGraph field "%s" is missing.', $ogField);
            }
        }

        // Twitter checks
        foreach (['twitter:title', 'twitter:description'] as $twitterField) {
            if (isset($content->metaTags[$twitterField]) && '' !== $content->metaTags[$twitterField]) {
                $score += self::FIELD_WEIGHTS[$twitterField];
                $details[$twitterField] = ['present' => true];
            } else {
                $details[$twitterField] = ['present' => false];
                $suggestions[] = sprintf('Twitter card field "%s" is missing.', $twitterField);
            }
        }

        // Canonical check
        if (isset($content->metaTags['canonical']) && '' !== $content->metaTags['canonical']) {
            $score += self::FIELD_WEIGHTS['canonical'];
            $details['canonical'] = ['present' => true];
        } else {
            $details['canonical'] = ['present' => false];
            $suggestions[] = 'Canonical URL is not set.';
        }

        // Schema.org check
        if ([] !== $content->schemaOrgData) {
            $score += self::FIELD_WEIGHTS['schema.org'];
            $details['schema.org'] = ['present' => true];
        } else {
            $details['schema.org'] = ['present' => false];
            $suggestions[] = 'No structured data (schema.org) found.';
        }

        $normalizedScore = $maxScore > 0 ? ($score / $maxScore) * 100.0 : 0.0;

        return new AnalysisResult(
            analyzerIdentifier: $this->getIdentifier(),
            score: round($normalizedScore, 2),
            details: $details,
            suggestions: $suggestions,
        );
    }
}
