<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Tests\Unit\Domain\Model;

use Koh\ContentIntelligence\Domain\Model\ContentSnapshot;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContentSnapshotTest extends TestCase
{
    #[Test]
    public function canBeConstructedWithAllFields(): void
    {
        $bodyTexts = ['First paragraph', 'Second paragraph'];
        $headings = ['Main Title', 'Subtitle'];
        $metaTags = ['description' => 'A page description', 'keywords' => 'test, page'];
        $schemaOrgData = ['@type' => 'WebPage', 'name' => 'Test'];
        $lastModified = new \DateTimeImmutable('2026-03-23 08:30:00');

        $snapshot = new ContentSnapshot(
            pageUid: 42,
            languageUid: 1,
            title: 'Test Page',
            description: 'A page description',
            bodyTexts: $bodyTexts,
            headings: $headings,
            metaTags: $metaTags,
            schemaOrgData: $schemaOrgData,
            lastModified: $lastModified,
        );

        self::assertSame(42, $snapshot->pageUid);
        self::assertSame(1, $snapshot->languageUid);
        self::assertSame('Test Page', $snapshot->title);
        self::assertSame('A page description', $snapshot->description);
        self::assertSame($bodyTexts, $snapshot->bodyTexts);
        self::assertSame($headings, $snapshot->headings);
        self::assertSame($metaTags, $snapshot->metaTags);
        self::assertSame($schemaOrgData, $snapshot->schemaOrgData);
        self::assertSame($lastModified, $snapshot->lastModified);
    }

    #[Test]
    public function canBeConstructedWithEmptyArrays(): void
    {
        $snapshot = new ContentSnapshot(
            pageUid: 1,
            languageUid: 0,
            title: '',
            description: '',
            bodyTexts: [],
            headings: [],
            metaTags: [],
            schemaOrgData: [],
            lastModified: new \DateTimeImmutable(),
        );

        self::assertSame([], $snapshot->bodyTexts);
        self::assertSame([], $snapshot->headings);
        self::assertSame([], $snapshot->metaTags);
        self::assertSame([], $snapshot->schemaOrgData);
    }

    #[Test]
    public function defaultLanguageUidCanBeZero(): void
    {
        $snapshot = new ContentSnapshot(
            pageUid: 5,
            languageUid: 0,
            title: 'Default Language Page',
            description: 'Description',
            bodyTexts: ['text'],
            headings: ['heading'],
            metaTags: [],
            schemaOrgData: [],
            lastModified: new \DateTimeImmutable(),
        );

        self::assertSame(0, $snapshot->languageUid);
    }
}
