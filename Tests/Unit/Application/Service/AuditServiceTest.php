<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Tests\Unit\Application\Service;

use Koh\ContentIntelligence\Application\Event\AuditEntryCreatedEvent;
use Koh\ContentIntelligence\Application\Service\AuditService;
use Koh\ContentIntelligence\Domain\Contract\AuditEntryRepositoryInterface;
use Koh\ContentIntelligence\Domain\Model\AuditEntry;
use Koh\ContentIntelligence\Domain\Model\ContentOrigin;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

final class AuditServiceTest extends TestCase
{
    private AuditEntryRepositoryInterface&MockObject $repository;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private AuditService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(AuditEntryRepositoryInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->subject = new AuditService(
            $this->repository,
            $this->eventDispatcher,
        );
    }

    #[Test]
    public function trackCreatesAndSavesAuditEntry(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (AuditEntry $entry): bool {
                return 10 === $entry->pageUid
                    && 100 === $entry->contentUid
                    && 'tt_content' === $entry->tableName
                    && 'bodytext' === $entry->fieldName
                    && ContentOrigin::AiGenerated === $entry->origin
                    && 'openai' === $entry->aiProvider
                    && 'gpt-4' === $entry->aiModel
                    && 0 === $entry->reviewedBy
                    && null === $entry->reviewedAt;
            }));

        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(fn(object $event) => $event);

        $entry = $this->subject->track(
            pageUid: 10,
            contentUid: 100,
            tableName: 'tt_content',
            fieldName: 'bodytext',
            origin: ContentOrigin::AiGenerated,
            aiProvider: 'openai',
            aiModel: 'gpt-4',
        );

        self::assertSame(10, $entry->pageUid);
        self::assertSame(100, $entry->contentUid);
        self::assertSame('tt_content', $entry->tableName);
        self::assertSame('bodytext', $entry->fieldName);
        self::assertSame(ContentOrigin::AiGenerated, $entry->origin);
        self::assertSame('openai', $entry->aiProvider);
        self::assertSame('gpt-4', $entry->aiModel);
        self::assertSame(0, $entry->reviewedBy);
        self::assertNull($entry->reviewedAt);
        self::assertInstanceOf(\DateTimeImmutable::class, $entry->createdAt);
    }

    #[Test]
    public function trackDispatchesAuditEntryCreatedEvent(): void
    {
        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function (object $event): bool {
                return $event instanceof AuditEntryCreatedEvent
                    && 10 === $event->entry->pageUid
                    && 100 === $event->entry->contentUid;
            }))
            ->willReturnCallback(fn(object $event) => $event);

        $this->subject->track(
            pageUid: 10,
            contentUid: 100,
            tableName: 'tt_content',
            fieldName: 'bodytext',
            origin: ContentOrigin::AiAssisted,
        );
    }

    #[Test]
    public function getPageAuditLogDelegatesToRepository(): void
    {
        $entries = [
            new AuditEntry(
                pageUid: 10,
                contentUid: 100,
                tableName: 'tt_content',
                fieldName: 'bodytext',
                origin: ContentOrigin::Human,
                aiProvider: '',
                aiModel: '',
                reviewedBy: 0,
                reviewedAt: null,
                createdAt: new \DateTimeImmutable(),
            ),
        ];

        $this->repository
            ->expects(self::once())
            ->method('findByPage')
            ->with(10)
            ->willReturn($entries);

        $result = $this->subject->getPageAuditLog(10);

        self::assertSame($entries, $result);
    }

    #[Test]
    public function getUnreviewedEntriesDelegatesToRepositoryWithLimit(): void
    {
        $entries = [
            new AuditEntry(
                pageUid: 5,
                contentUid: 50,
                tableName: 'tt_content',
                fieldName: 'header',
                origin: ContentOrigin::AiGenerated,
                aiProvider: 'anthropic',
                aiModel: 'claude-3',
                reviewedBy: 0,
                reviewedAt: null,
                createdAt: new \DateTimeImmutable(),
            ),
        ];

        $this->repository
            ->expects(self::once())
            ->method('findUnreviewed')
            ->with(25)
            ->willReturn($entries);

        $result = $this->subject->getUnreviewedEntries(25);

        self::assertSame($entries, $result);
    }
}
