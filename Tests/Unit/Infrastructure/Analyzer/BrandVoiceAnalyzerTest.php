<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Tests\Unit\Infrastructure\Analyzer;

use Koh\AiWorkflows\Domain\Contract\AiClientInterface;
use Koh\ContentIntelligence\Domain\Model\BrandVoiceProfile;
use Koh\ContentIntelligence\Domain\Model\ContentSnapshot;
use Koh\ContentIntelligence\Domain\Contract\BrandVoiceConfigurationProviderInterface;
use Koh\ContentIntelligence\Infrastructure\Analyzer\BrandVoiceAnalyzer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class BrandVoiceAnalyzerTest extends TestCase
{
    private AiClientInterface&MockObject $aiClient;
    private BrandVoiceConfigurationProviderInterface&MockObject $configProvider;
    private BrandVoiceAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aiClient = $this->createMock(AiClientInterface::class);
        $this->configProvider = $this->createMock(BrandVoiceConfigurationProviderInterface::class);
        $this->analyzer = new BrandVoiceAnalyzer($this->aiClient, $this->configProvider);
    }

    #[Test]
    public function returnsCorrectIdentifier(): void
    {
        self::assertSame('brand_voice', $this->analyzer->getIdentifier());
    }

    #[Test]
    public function returnsCorrectName(): void
    {
        self::assertSame('Brand Voice Consistency', $this->analyzer->getName());
    }

    #[Test]
    public function returnsWeight015(): void
    {
        self::assertSame(0.15, $this->analyzer->getWeight());
    }

    #[Test]
    public function detectsForbiddenTerms(): void
    {
        $profile = BrandVoiceProfile::create('test')
            ->forbiddenTerms(['cheap', 'buy now']);

        $this->configProvider
            ->method('getProfile')
            ->willReturn($profile);

        $this->aiClient
            ->method('generate')
            ->willReturn(json_encode([
                'toneScore' => 100,
                'violations' => [],
            ], JSON_THROW_ON_ERROR));

        $snapshot = $this->createSnapshot([
            'bodyTexts' => ['This is a cheap product you should buy now!'],
        ]);

        $result = $this->analyzer->analyze($snapshot);

        self::assertArrayHasKey('forbiddenTermsFound', $result->details);
        self::assertContains('cheap', $result->details['forbiddenTermsFound']);
        self::assertContains('buy now', $result->details['forbiddenTermsFound']);

        $forbiddenSuggestions = array_filter($result->suggestions, static fn(string $s): bool => str_contains($s, 'Forbidden terms'));
        self::assertNotEmpty($forbiddenSuggestions);
    }

    #[Test]
    public function detectsMissingRequiredTerms(): void
    {
        $profile = BrandVoiceProfile::create('test')
            ->requiredTerms(['innovation', 'sustainability']);

        $this->configProvider
            ->method('getProfile')
            ->willReturn($profile);

        $this->aiClient
            ->method('generate')
            ->willReturn(json_encode([
                'toneScore' => 100,
                'violations' => [],
            ], JSON_THROW_ON_ERROR));

        $snapshot = $this->createSnapshot([
            'bodyTexts' => ['Our company delivers great products.'],
        ]);

        $result = $this->analyzer->analyze($snapshot);

        self::assertArrayHasKey('requiredTermsMissing', $result->details);
        self::assertContains('innovation', $result->details['requiredTermsMissing']);
        self::assertContains('sustainability', $result->details['requiredTermsMissing']);

        $missingSuggestions = array_filter($result->suggestions, static fn(string $s): bool => str_contains($s, 'Required terms missing'));
        self::assertNotEmpty($missingSuggestions);
    }

    /**
     * The sentence-length rule is the only check that has to look inside a
     * sentence rather than at the text as a whole. If the word count were
     * compared against the wrong side of the limit, or short sentences counted
     * too, every page would be penalised for a rule it does not break.
     */
    #[Test]
    public function countsOnlySentencesThatExceedTheConfiguredLength(): void
    {
        $profile = BrandVoiceProfile::create('test')
            ->maxSentenceLength(5);

        $this->configProvider
            ->method('getProfile')
            ->willReturn($profile);

        $this->aiClient
            ->method('generate')
            ->willReturn(json_encode([
                'toneScore' => 100,
                'violations' => [],
            ], JSON_THROW_ON_ERROR));

        $snapshot = $this->createSnapshot([
            'bodyTexts' => ['This sentence carries clearly more than five words in it. Short enough.'],
        ]);

        $result = $this->analyzer->analyze($snapshot);

        self::assertSame(1, $result->details['longSentences'], 'the second sentence stays under the limit and must not be counted');

        $lengthSuggestions = array_filter($result->suggestions, static fn(string $s): bool => str_contains($s, 'exceed the maximum length'));
        self::assertNotEmpty($lengthSuggestions);

        // 3 points off the rule-based 100, then blended 60/40 with the AI tone score.
        self::assertSame(98.2, $result->score);
    }

    /**
     * The AI half of the analysis contributes both a tone score and free-text
     * violations. The score has to be blended rather than replaced — otherwise
     * the rule-based findings would silently disappear — and only violations
     * that actually carry a suggestion may reach the editor, since the model is
     * free to return entries without one.
     */
    #[Test]
    public function aiToneScoreIsBlendedInAndOnlyActionableViolationsBecomeSuggestions(): void
    {
        $profile = BrandVoiceProfile::create('test');

        $this->configProvider
            ->method('getProfile')
            ->willReturn($profile);

        $this->aiClient
            ->method('generate')
            ->willReturn(json_encode([
                'toneScore' => 60,
                'violations' => [
                    ['text' => 'Hol dir jetzt', 'rule' => 'addressForm', 'suggestion' => 'Use the formal address form.'],
                    ['text' => 'Grosse Auswahl', 'rule' => 'tone'],
                    'not a violation object',
                ],
            ], JSON_THROW_ON_ERROR));

        $snapshot = $this->createSnapshot([
            'bodyTexts' => ['Hol dir jetzt die grosse Auswahl.'],
        ]);

        $result = $this->analyzer->analyze($snapshot);

        self::assertSame(60.0, $result->details['aiToneScore']);
        // 100 from the rule-based checks, weighted 0.6, plus 60 weighted 0.4.
        self::assertSame(84.0, $result->score);
        self::assertSame(['Use the formal address form.'], $result->suggestions);
        self::assertCount(3, $result->details['violations'], 'the raw violations stay in the details even when they yield no suggestion');
    }

    #[Test]
    public function emptyContentReturns0Score(): void
    {
        $profile = BrandVoiceProfile::create('test');

        $this->configProvider
            ->method('getProfile')
            ->willReturn($profile);

        $snapshot = $this->createSnapshot([
            'bodyTexts' => [],
        ]);

        $result = $this->analyzer->analyze($snapshot);

        self::assertSame(0.0, $result->score);
        self::assertSame('No content to analyze', $result->details['error']);
    }

    #[Test]
    public function handlesAiClientFailureGracefully(): void
    {
        $profile = BrandVoiceProfile::create('test');

        $this->configProvider
            ->method('getProfile')
            ->willReturn($profile);

        $this->aiClient
            ->method('generate')
            ->willThrowException(new \RuntimeException('AI service unavailable'));

        $snapshot = $this->createSnapshot([
            'bodyTexts' => ['Some valid content to analyze.'],
        ]);

        $result = $this->analyzer->analyze($snapshot);

        self::assertArrayHasKey('aiError', $result->details);
        self::assertSame('AI service unavailable', $result->details['aiError']);
        // Rule-based score of 100 is kept (no forbidden/required terms configured)
        self::assertSame(100.0, $result->score);
    }

    private function createSnapshot(array $overrides = []): ContentSnapshot
    {
        return new ContentSnapshot(
            pageUid: $overrides['pageUid'] ?? 1,
            languageUid: $overrides['languageUid'] ?? 0,
            title: $overrides['title'] ?? 'Test Page',
            description: $overrides['description'] ?? 'Test description for the page that is long enough',
            bodyTexts: $overrides['bodyTexts'] ?? ['Some body text content.'],
            headings: $overrides['headings'] ?? ['Heading 1', 'Heading 2'],
            metaTags: $overrides['metaTags'] ?? [],
            schemaOrgData: $overrides['schemaOrgData'] ?? [],
            lastModified: $overrides['lastModified'] ?? new \DateTimeImmutable(),
        );
    }
}
