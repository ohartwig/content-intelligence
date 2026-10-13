<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Tests\Unit\Infrastructure\Analyzer;

use Koh\AiWorkflows\Domain\Contract\AiClientInterface;
use Koh\ContentIntelligence\Domain\Model\ContentSnapshot;
use Koh\ContentIntelligence\Infrastructure\Analyzer\FreshnessAnalyzer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class FreshnessAnalyzerTest extends TestCase
{
    private AiClientInterface&MockObject $aiClient;
    private FreshnessAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aiClient = $this->createMock(AiClientInterface::class);
        $this->analyzer = new FreshnessAnalyzer($this->aiClient);
    }

    #[Test]
    public function returnsCorrectIdentifier(): void
    {
        self::assertSame('freshness', $this->analyzer->getIdentifier());
    }

    #[Test]
    public function returnsCorrectName(): void
    {
        self::assertSame('Content Freshness', $this->analyzer->getName());
    }

    #[Test]
    public function returnsWeight020(): void
    {
        self::assertSame(0.20, $this->analyzer->getWeight());
    }

    #[Test]
    public function oldContentReducesScore(): void
    {
        $snapshot = $this->createSnapshot([
            'lastModified' => new \DateTimeImmutable('-400 days'),
            'bodyTexts' => ['Some content that was written long ago.'],
        ]);

        $this->aiClient
            ->method('generate')
            ->willReturn(json_encode([
                'outdatedSignals' => [],
                'freshnessScore' => 100,
            ], JSON_THROW_ON_ERROR));

        $result = $this->analyzer->analyze($snapshot);

        self::assertSame('freshness', $result->analyzerIdentifier);
        // Age score: 100 - 40 = 60 (>365 days)
        // Blended: (60 * 0.5) + (100 * 0.5) = 80.0
        self::assertSame(80.0, $result->score);
        self::assertGreaterThan(365, $result->details['daysSinceModified']);
    }

    #[Test]
    public function aiAnalysisBlendsWithAgeScore(): void
    {
        $snapshot = $this->createSnapshot([
            'lastModified' => new \DateTimeImmutable('-10 days'),
            'bodyTexts' => ['Content referencing 2019 technology stack.'],
        ]);

        $this->aiClient
            ->method('generate')
            ->willReturn(json_encode([
                'outdatedSignals' => [
                    ['text' => '2019 technology stack', 'reason' => 'Refers to old year'],
                ],
                'freshnessScore' => 40,
            ], JSON_THROW_ON_ERROR));

        $result = $this->analyzer->analyze($snapshot);

        // Age score: 100 (< 180 days, no penalty)
        // Blended: (100 * 0.5) + (40 * 0.5) = 70.0
        self::assertSame(70.0, $result->score);
        self::assertArrayHasKey('aiAnalysis', $result->details);
        self::assertNotEmpty($result->suggestions);
    }

    #[Test]
    public function handlesAiClientFailureGracefully(): void
    {
        $snapshot = $this->createSnapshot([
            'lastModified' => new \DateTimeImmutable('-200 days'),
            'bodyTexts' => ['Some content here.'],
        ]);

        $this->aiClient
            ->method('generate')
            ->willThrowException(new \RuntimeException('AI service unavailable'));

        $result = $this->analyzer->analyze($snapshot);

        // Age score: 100 - 20 = 80 (> 180 days warning)
        // No blending since AI failed
        self::assertSame(80.0, $result->score);
        self::assertArrayHasKey('aiError', $result->details);
        self::assertSame('AI service unavailable', $result->details['aiError']);
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
