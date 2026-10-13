<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Tests\Unit\Domain\Model;

use Koh\ContentIntelligence\Domain\Model\AuditEntry;
use Koh\ContentIntelligence\Domain\Model\ContentOrigin;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AuditEntryTest extends TestCase
{
    #[Test]
    public function canBeConstructedWithAllFieldsIncludingReviewedAt(): void
    {
        $reviewedAt = new \DateTimeImmutable('2026-03-22 14:00:00');
        $createdAt = new \DateTimeImmutable('2026-03-20 09:00:00');

        $entry = new AuditEntry(
            pageUid: 42,
            contentUid: 101,
            tableName: 'tt_content',
            fieldName: 'bodytext',
            origin: ContentOrigin::AiGenerated,
            aiProvider: 'openai',
            aiModel: 'gpt-4',
            reviewedBy: 3,
            reviewedAt: $reviewedAt,
            createdAt: $createdAt,
        );

        self::assertSame(42, $entry->pageUid);
        self::assertSame(101, $entry->contentUid);
        self::assertSame('tt_content', $entry->tableName);
        self::assertSame('bodytext', $entry->fieldName);
        self::assertSame(ContentOrigin::AiGenerated, $entry->origin);
        self::assertSame('openai', $entry->aiProvider);
        self::assertSame('gpt-4', $entry->aiModel);
        self::assertSame(3, $entry->reviewedBy);
        self::assertSame($reviewedAt, $entry->reviewedAt);
        self::assertSame($createdAt, $entry->createdAt);
    }

    #[Test]
    public function canBeConstructedWithNullReviewedAt(): void
    {
        $createdAt = new \DateTimeImmutable('2026-03-20 09:00:00');

        $entry = new AuditEntry(
            pageUid: 10,
            contentUid: 55,
            tableName: 'pages',
            fieldName: 'title',
            origin: ContentOrigin::AiAssisted,
            aiProvider: 'anthropic',
            aiModel: 'claude-opus-4-6',
            reviewedBy: 0,
            reviewedAt: null,
            createdAt: $createdAt,
        );

        self::assertNull($entry->reviewedAt);
        self::assertSame(0, $entry->reviewedBy);
    }

    #[Test]
    public function supportsHumanOrigin(): void
    {
        $entry = new AuditEntry(
            pageUid: 1,
            contentUid: 1,
            tableName: 'tt_content',
            fieldName: 'header',
            origin: ContentOrigin::Human,
            aiProvider: '',
            aiModel: '',
            reviewedBy: 1,
            reviewedAt: null,
            createdAt: new \DateTimeImmutable(),
        );

        self::assertSame(ContentOrigin::Human, $entry->origin);
        self::assertSame('', $entry->aiProvider);
        self::assertSame('', $entry->aiModel);
    }
}
