<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Infrastructure\Analyzer;

use Koh\AiWorkflows\Domain\Contract\AiClientInterface;
use Koh\ContentIntelligence\Domain\Contract\AnalyzerInterface;
use Koh\ContentIntelligence\Domain\Model\AnalysisResult;
use Koh\ContentIntelligence\Domain\Model\ContentSnapshot;
use Koh\ContentIntelligence\Domain\Contract\BrandVoiceConfigurationProviderInterface;

final readonly class BrandVoiceAnalyzer implements AnalyzerInterface
{
    public function __construct(
        private AiClientInterface $aiClient,
        private BrandVoiceConfigurationProviderInterface $configProvider,
    ) {}

    public function getIdentifier(): string
    {
        return 'brand_voice';
    }

    public function getName(): string
    {
        return 'Brand Voice Consistency';
    }

    public function getWeight(): float
    {
        return 0.15;
    }

    public function analyze(ContentSnapshot $content): AnalysisResult
    {
        $profile = $this->configProvider->getProfile();
        $profileData = $profile->toArray();
        $details = [];
        $suggestions = [];
        $score = 100.0;

        // Rule-based checks first
        $combinedText = implode("\n", $content->bodyTexts);
        if ('' === $combinedText) {
            return new AnalysisResult(
                analyzerIdentifier: $this->getIdentifier(),
                score: 0.0,
                details: ['error' => 'No content to analyze'],
                suggestions: ['Add text content to enable brand voice analysis.'],
            );
        }

        // Check forbidden terms
        $forbiddenFound = [];
        foreach ($profile->getForbiddenTerms() as $term) {
            if (false !== mb_stripos($combinedText, $term)) {
                $forbiddenFound[] = $term;
            }
        }
        if ([] !== $forbiddenFound) {
            $penalty = min(30.0, count($forbiddenFound) * 10.0);
            $score -= $penalty;
            $details['forbiddenTermsFound'] = $forbiddenFound;
            $suggestions[] = sprintf('Forbidden terms found: %s', implode(', ', $forbiddenFound));
        }

        // Check required terms
        $requiredMissing = [];
        foreach ($profile->getRequiredTerms() as $term) {
            if (false === mb_stripos($combinedText, $term)) {
                $requiredMissing[] = $term;
            }
        }
        if ([] !== $requiredMissing) {
            $penalty = min(20.0, count($requiredMissing) * 5.0);
            $score -= $penalty;
            $details['requiredTermsMissing'] = $requiredMissing;
            $suggestions[] = sprintf('Required terms missing: %s', implode(', ', $requiredMissing));
        }

        // Check sentence length
        $maxLength = $profile->getMaxSentenceLength();
        if (null !== $maxLength) {
            $sentences = preg_split('/[.!?]+/u', $combinedText, -1, PREG_SPLIT_NO_EMPTY);
            $longSentences = 0;
            if (is_array($sentences)) {
                foreach ($sentences as $sentence) {
                    $wordCount = str_word_count(trim($sentence));
                    if ($wordCount > $maxLength) {
                        ++$longSentences;
                    }
                }
            }
            if ($longSentences > 0) {
                $penalty = min(15.0, $longSentences * 3.0);
                $score -= $penalty;
                $details['longSentences'] = $longSentences;
                $suggestions[] = sprintf('%d sentence(s) exceed the maximum length of %d words.', $longSentences, $maxLength);
            }
        }

        // AI-based tone analysis
        $truncatedText = mb_substr($combinedText, 0, 2000);
        $profileJson = json_encode($profileData, JSON_THROW_ON_ERROR);

        $systemPrompt = 'You are a brand voice consistency analyzer. Analyze text against a brand voice profile. Always respond with valid JSON only.';
        $userPrompt = "Analyze the following content for brand voice consistency against this brand profile:\n\nBrand Profile:\n" . $profileJson . "\n\nContent:\n" . $truncatedText . "\n\nCheck for:\n1. Tone consistency with defined tone attributes\n2. Address form compliance\n3. Overall brand voice alignment\n\nRespond in JSON format: {\"toneScore\": 0-100, \"violations\": [{\"text\": \"...\", \"rule\": \"...\", \"suggestion\": \"...\"}]}";
        $prompt = $systemPrompt . "\n\n" . $userPrompt;

        try {
            $response = $this->aiClient->generate($prompt, ['temperature' => 0.1, 'max_tokens' => 1500]);
            $aiResult = json_decode($response, true);

            if (is_array($aiResult)) {
                $toneScore = (float) ($aiResult['toneScore'] ?? 100);
                // Blend rule-based score with AI tone score
                $score = ($score * 0.6) + ($toneScore * 0.4);
                $details['aiToneScore'] = $toneScore;

                if (isset($aiResult['violations']) && is_array($aiResult['violations'])) {
                    $details['violations'] = $aiResult['violations'];
                    foreach ($aiResult['violations'] as $violation) {
                        if (is_array($violation) && isset($violation['suggestion'])) {
                            $suggestions[] = (string) $violation['suggestion'];
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $details['aiError'] = $e->getMessage();
        }

        return new AnalysisResult(
            analyzerIdentifier: $this->getIdentifier(),
            score: max(0.0, min(100.0, round($score, 2))),
            details: $details,
            suggestions: $suggestions,
        );
    }
}
