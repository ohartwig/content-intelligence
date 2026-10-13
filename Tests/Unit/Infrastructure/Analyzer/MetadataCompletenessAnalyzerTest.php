<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Tests\Unit\Infrastructure\Analyzer;

use Koh\ContentIntelligence\Domain\Model\ContentSnapshot;
use Koh\ContentIntelligence\Infrastructure\Analyzer\MetadataCompletenessAnalyzer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MetadataCompletenessAnalyzerTest extends TestCase
{
    private MetadataCompletenessAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new MetadataCompletenessAnalyzer();
    }

    #[Test]
    public function returnsCorrectIdentifier(): void
    {
        self::assertSame('metadata_completeness', $this->analyzer->getIdentifier());
    }

    #[Test]
    public function returnsCorrectName(): void
    {
        self::assertSame('Metadata Completeness', $this->analyzer->getName());
    }

    #[Test]
    public function returnsWeight025(): void
    {
        self::assertSame(0.25, $this->analyzer->getWeight());
    }

    #[Test]
    public function fullMetadataGives100PercentScore(): void
    {
        $snapshot = $this->createSnapshot([
            'title' => 'A proper page title here', // 24 chars, within 10-70
            'description' => str_repeat('A proper meta description that is long enough to pass the minimum threshold. ', 2), // > 50, < 160
            'metaTags' => [
                'og:title' => 'OG Title',
                'og:description' => 'OG Description',
                'og:image' => 'https://example.com/image.jpg',
                'twitter:title' => 'Twitter Title',
                'twitter:description' => 'Twitter Description',
                'canonical' => 'https://example.com/page',
            ],
            'schemaOrgData' => ['@type' => 'WebPage'],
        ]);

        $result = $this->analyzer->analyze($snapshot);

        self::assertSame('metadata_completeness', $result->analyzerIdentifier);
        self::assertSame(100.0, $result->score);
        self::assertSame([], $result->suggestions);
    }

    #[Test]
    public function missingAllMetadataGives0PercentScore(): void
    {
        $snapshot = $this->createSnapshot([
            'title' => '',
            'description' => '',
            'metaTags' => [],
            'schemaOrgData' => [],
        ]);

        $result = $this->analyzer->analyze($snapshot);

        self::assertSame(0.0, $result->score);
        self::assertNotEmpty($result->suggestions);
    }

    #[Test]
    public function partialMetadataGivesProportionalScore(): void
    {
        // Only title and description present (with valid lengths), nothing else
        $snapshot = $this->createSnapshot([
            'title' => 'A valid title for testing', // within 10-70
            'description' => 'A valid meta description that is at least fifty characters long for testing purposes.', // within 50-160
            'metaTags' => [],
            'schemaOrgData' => [],
        ]);

        $result = $this->analyzer->analyze($snapshot);

        // title=15 + description=15 = 30 out of 95 total weight
        $expectedScore = round((30.0 / 95.0) * 100.0, 2);
        self::assertSame($expectedScore, $result->score);
        self::assertNotEmpty($result->suggestions);
    }

    #[Test]
    public function titleTooShortGetsHalfPointsWithSuggestion(): void
    {
        $snapshot = $this->createSnapshot([
            'title' => 'Short', // 5 chars, below MIN_TITLE_LENGTH=10
            'description' => '',
            'metaTags' => [],
            'schemaOrgData' => [],
        ]);

        $result = $this->analyzer->analyze($snapshot);

        // Title gets half points (7.5 of 15)
        self::assertTrue($result->details['title']['present']);
        self::assertSame(5, $result->details['title']['length']);

        $titleSuggestions = array_filter($result->suggestions, static fn(string $s): bool => str_contains($s, 'Title is too short'));
        self::assertNotEmpty($titleSuggestions);
    }

    #[Test]
    public function descriptionTooLongGetsHalfPointsWithSuggestion(): void
    {
        $snapshot = $this->createSnapshot([
            'title' => 'A valid page title here',
            'description' => str_repeat('This is a very long description. ', 10), // > 160 chars
            'metaTags' => [],
            'schemaOrgData' => [],
        ]);

        $result = $this->analyzer->analyze($snapshot);

        self::assertTrue($result->details['description']['present']);

        $descSuggestions = array_filter($result->suggestions, static fn(string $s): bool => str_contains($s, 'Meta description is too long'));
        self::assertNotEmpty($descSuggestions);
    }

    #[Test]
    public function missingOgTagsGenerateSuggestions(): void
    {
        $snapshot = $this->createSnapshot([
            'title' => 'A valid page title here',
            'description' => 'A valid meta description that is at least fifty characters long for testing purposes.',
            'metaTags' => [
                'canonical' => 'https://example.com/page',
            ],
            'schemaOrgData' => ['@type' => 'WebPage'],
        ]);

        $result = $this->analyzer->analyze($snapshot);

        $ogSuggestions = array_filter($result->suggestions, static fn(string $s): bool => str_contains($s, 'OpenGraph'));
        self::assertCount(3, $ogSuggestions); // og:title, og:description, og:image

        $twitterSuggestions = array_filter($result->suggestions, static fn(string $s): bool => str_contains($s, 'Twitter'));
        self::assertCount(2, $twitterSuggestions); // twitter:title, twitter:description
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
