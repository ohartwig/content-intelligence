<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Infrastructure\Analyzer;

use Koh\AiWorkflows\Domain\Contract\AiClientInterface;
use Koh\ContentIntelligence\Domain\Contract\AnalyzerInterface;
use Koh\ContentIntelligence\Domain\Model\AnalysisResult;
use Koh\ContentIntelligence\Domain\Model\ContentSnapshot;

final readonly class FreshnessAnalyzer implements AnalyzerInterface
{
    private const MAX_AGE_DAYS_CRITICAL = 365;
    private const MAX_AGE_DAYS_WARNING = 180;

    public function __construct(
        private AiClientInterface $aiClient,
    ) {}

    public function getIdentifier(): string
    {
        return 'freshness';
    }

    public function getName(): string
    {
        return 'Content Freshness';
    }

    public function getWeight(): float
    {
        return 0.20;
    }

    public function analyze(ContentSnapshot $content): AnalysisResult
    {
        $details = [];
        $suggestions = [];
        $score = 100.0;

        // Age-based scoring
        $now = new \DateTimeImmutable();
        $daysSinceModified = (int) $now->diff($content->lastModified)->days;
        $details['daysSinceModified'] = $daysSinceModified;

        if ($daysSinceModified > self::MAX_AGE_DAYS_CRITICAL) {
            $score -= 40.0;
            $suggestions[] = sprintf('Content was last modified %d days ago. Consider reviewing and updating.', $daysSinceModified);
        } elseif ($daysSinceModified > self::MAX_AGE_DAYS_WARNING) {
            $score -= 20.0;
            $suggestions[] = sprintf('Content was last modified %d days ago. A review may be beneficial.', $daysSinceModified);
        }

        // AI-based temporal signal analysis
        $combinedText = implode("\n", $content->bodyTexts);
        if ('' !== $combinedText) {
            $truncatedText = mb_substr($combinedText, 0, 2000);

            $systemPrompt = 'You are a content freshness analyzer. Analyze text for temporal signals indicating outdated content. Always respond with valid JSON only.';
            $userPrompt = "Analyze this content for temporal signals that might indicate outdated information. Look for:\n- References to specific years, dates, or timeframes\n- Mentions of technologies, versions, or products that may be outdated\n- Seasonal or event-based references\n- Phrases like 'recently', 'this year', 'currently'\n\nContent:\n" . $truncatedText . "\n\nRespond in JSON format: {\"outdatedSignals\": [{\"text\": \"...\", \"reason\": \"...\"}], \"freshnessScore\": 0-100}";
            $prompt = $systemPrompt . "\n\n" . $userPrompt;

            try {
                $response = $this->aiClient->generate($prompt, ['temperature' => 0.1, 'max_tokens' => 1000]);
                $aiResult = json_decode($response, true);

                if (is_array($aiResult)) {
                    $aiScore = (float) ($aiResult['freshnessScore'] ?? 100);
                    $details['aiAnalysis'] = $aiResult;

                    // Blend age-based and AI-based scores
                    $score = ($score * 0.5) + ($aiScore * 0.5);

                    if (isset($aiResult['outdatedSignals']) && is_array($aiResult['outdatedSignals'])) {
                        foreach ($aiResult['outdatedSignals'] as $signal) {
                            if (is_array($signal) && isset($signal['text'], $signal['reason'])) {
                                $suggestions[] = sprintf('Potentially outdated: "%s" — %s', $signal['text'], $signal['reason']);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                $details['aiError'] = $e->getMessage();
            }
        }

        return new AnalysisResult(
            analyzerIdentifier: $this->getIdentifier(),
            score: max(0.0, min(100.0, round($score, 2))),
            details: $details,
            suggestions: $suggestions,
        );
    }
}
