<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Tests\Unit\Infrastructure\Analyzer;

use Koh\ContentIntelligence\Domain\Model\ContentSnapshot;
use Koh\ContentIntelligence\Infrastructure\Analyzer\StructureQualityAnalyzer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StructureQualityAnalyzerTest extends TestCase
{
    private StructureQualityAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new StructureQualityAnalyzer();
    }

    #[Test]
    public function returnsCorrectIdentifier(): void
    {
        self::assertSame('structure_quality', $this->analyzer->getIdentifier());
    }

    #[Test]
    public function returnsCorrectName(): void
    {
        self::assertSame('Structure Quality', $this->analyzer->getName());
    }

    #[Test]
    public function returnsWeight025(): void
    {
        self::assertSame(0.25, $this->analyzer->getWeight());
    }

    #[Test]
    public function wellStructuredContentGivesHighScore(): void
    {
        $snapshot = $this->createSnapshot([
            'title' => 'Well Structured Page',
            'description' => 'A good description for the page.',
            'bodyTexts' => [
                str_repeat('Content block one with enough text. ', 5),
                str_repeat('Content block two with enough text. ', 5),
                str_repeat('Content block three with enough text. ', 5),
            ],
            'headings' => ['Heading 1', 'Heading 2', 'Heading 3'],
        ]);

        $result = $this->analyzer->analyze($snapshot);

        self::assertSame('structure_quality', $result->analyzerIdentifier);
        // 30 (content >= 100 chars) + 30 (2-10 headings) + 20 (>= 3 blocks) + 10 (title) + 10 (desc) = 100
        self::assertSame(100.0, $result->score);
        self::assertSame([], $result->suggestions);
    }

    #[Test]
    public function emptyContentGivesLowScore(): void
    {
        $snapshot = $this->createSnapshot([
            'title' => '',
            'description' => '',
            'bodyTexts' => [],
            'headings' => [],
        ]);

        $result = $this->analyzer->analyze($snapshot);

        // 0 content + 0 headings + 0 blocks + 0 title + 0 desc = 0
        self::assertSame(0.0, $result->score);
        self::assertNotEmpty($result->suggestions);
    }

    #[Test]
    public function missingHeadingsGeneratesSuggestions(): void
    {
        $snapshot = $this->createSnapshot([
            'title' => 'Test Page',
            'description' => 'Test description',
            'bodyTexts' => [str_repeat('Some content here. ', 10)],
            'headings' => [],
        ]);

        $result = $this->analyzer->analyze($snapshot);

        $headingSuggestions = array_filter($result->suggestions, static fn(string $s): bool => str_contains($s, 'headings') || str_contains($s, 'heading'));
        self::assertNotEmpty($headingSuggestions);
        // Score: 30 (content) + 0 (no headings) + 10 (1 block, <3) + 10 (title) + 10 (desc) = 60
        self::assertSame(60.0, $result->score);
    }

    #[Test]
    public function shortContentGeneratesSuggestions(): void
    {
        $snapshot = $this->createSnapshot([
            'title' => 'Test Page',
            'description' => 'Test description',
            'bodyTexts' => ['Short.'], // 6 chars, well below MIN_BODY_TEXT_LENGTH=100
            'headings' => ['Heading 1', 'Heading 2'],
        ]);

        $result = $this->analyzer->analyze($snapshot);

        $contentSuggestions = array_filter($result->suggestions, static fn(string $s): bool => str_contains($s, 'Content is short'));
        self::assertNotEmpty($contentSuggestions);
        // Content points: 30 * (6/100) = 1.8
        // Headings: 30 (2 headings, within 2-10)
        // Blocks: 10 (1 block)
        // Title: 10, Description: 10
        // Total: 1.8 + 30 + 10 + 10 + 10 = 61.8
        self::assertSame(61.8, $result->score);
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
