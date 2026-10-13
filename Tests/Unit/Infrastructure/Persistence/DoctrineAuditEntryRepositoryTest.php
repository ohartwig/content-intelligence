<?php

declare(strict_types=1);

namespace Koh\ContentIntelligence\Tests\Unit\Infrastructure\Persistence;

use Doctrine\DBAL\Result;
use Koh\ContentIntelligence\Domain\Model\AuditEntry;
use Koh\ContentIntelligence\Domain\Model\ContentOrigin;
use Koh\ContentIntelligence\Infrastructure\Persistence\DoctrineAuditEntryRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * The audit trail is the record of who let which AI text into the site, so the
 * mapping between row and entry is the part that has to hold: an origin that
 * does not survive the round trip, a review timestamp that turns into "reviewed
 * at 1970" or a missing column that fatals all falsify the log rather than just
 * making it look odd.
 */
final class DoctrineAuditEntryRepositoryTest extends TestCase
{
    private const TABLE = 'tx_contentintelligence_audit_entry';

    private ConnectionPool&MockObject $connectionPool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connectionPool = $this->createMock(ConnectionPool::class);
    }

    /**
     * Every column arrives from the database as a string, including the origin
     * and both timestamps. Handing those through unconverted would give the
     * dashboard an enum-shaped string and dates that no longer compare.
     */
    #[Test]
    public function rowsAreMappedBackIntoAuditEntries(): void
    {
        $this->connectionPool->expects(self::any())
            ->method('getQueryBuilderForTable')
            ->with(self::TABLE)
            ->willReturn($this->queryBuilderReturning([
                [
                    'page_uid' => '12',
                    'content_uid' => '345',
                    'table_name' => 'tt_content',
                    'field_name' => 'bodytext',
                    'origin' => 'ai_assisted',
                    'ai_provider' => 'anthropic',
                    'ai_model' => 'claude-opus-4',
                    'reviewed_by' => '7',
                    'reviewed_at' => '1700000000',
                    'created_at' => '1699999000',
                ],
            ]));

        $entries = new DoctrineAuditEntryRepository($this->connectionPool)->findByPage(12);

        self::assertCount(1, $entries);
        self::assertSame(12, $entries[0]->pageUid);
        self::assertSame(345, $entries[0]->contentUid);
        self::assertSame('tt_content', $entries[0]->tableName);
        self::assertSame('bodytext', $entries[0]->fieldName);
        self::assertSame(ContentOrigin::AiAssisted, $entries[0]->origin);
        self::assertSame('anthropic', $entries[0]->aiProvider);
        self::assertSame('claude-opus-4', $entries[0]->aiModel);
        self::assertSame(7, $entries[0]->reviewedBy);
        self::assertNotNull($entries[0]->reviewedAt);
        self::assertSame(1700000000, $entries[0]->reviewedAt->getTimestamp());
        self::assertSame(1699999000, $entries[0]->createdAt->getTimestamp());
    }

    /**
     * An entry nobody has looked at yet carries a zero review timestamp — and
     * older rows may not carry the column at all. Both have to become a null
     * review date: turning zero into a DateTimeImmutable would present every
     * unreviewed entry as reviewed on 1 January 1970.
     */
    #[Test]
    public function anEntryThatWasNeverReviewedHasNoReviewDate(): void
    {
        $this->connectionPool->method('getQueryBuilderForTable')
            ->willReturn($this->queryBuilderReturning([
                [
                    'page_uid' => 12,
                    'content_uid' => 345,
                    'table_name' => 'tt_content',
                    'field_name' => 'bodytext',
                    'origin' => 'ai_generated',
                    'ai_provider' => 'openai',
                    'ai_model' => 'gpt-5',
                    'reviewed_at' => 0,
                    'created_at' => 1699999000,
                ],
            ]));

        $entries = new DoctrineAuditEntryRepository($this->connectionPool)->findUnreviewed(10);

        self::assertNull($entries[0]->reviewedAt);
        self::assertSame(0, $entries[0]->reviewedBy, 'a missing reviewed_by column must not fatal but default to nobody');
        self::assertSame(ContentOrigin::AiGenerated, $entries[0]->origin);
    }

    /**
     * The audit trail is append-only and lives on the page it documents, so the
     * entry has to be written with pid and page_uid alike — an entry stored on
     * pid 0 would be invisible to every page-scoped query and to the backend.
     */
    #[Test]
    public function savingWritesTheEntryOntoThePageItDocuments(): void
    {
        $row = null;

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('insert')
            ->willReturnCallback(static function (string $table, array $data) use (&$row): int {
                $row = $data;

                return 1;
            });

        $this->connectionPool->expects(self::any())->method('getConnectionForTable')->with(self::TABLE)->willReturn($connection);

        new DoctrineAuditEntryRepository($this->connectionPool)->save(new AuditEntry(
            pageUid: 12,
            contentUid: 345,
            tableName: 'tt_content',
            fieldName: 'bodytext',
            origin: ContentOrigin::AiGenerated,
            aiProvider: 'openai',
            aiModel: 'gpt-5',
            reviewedBy: 0,
            reviewedAt: null,
            createdAt: new \DateTimeImmutable('@1699999000'),
        ));

        self::assertIsArray($row);
        self::assertSame(12, $row['pid']);
        self::assertSame(12, $row['page_uid']);
        self::assertSame('ai_generated', $row['origin'], 'the enum has to be stored as its backing value');
        self::assertSame(0, $row['reviewed_at'], 'an unreviewed entry must not be stored with a null timestamp');
        self::assertSame(1699999000, $row['created_at']);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function queryBuilderReturning(array $rows): QueryBuilder&MockObject
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn($rows);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('createNamedParameter')->willReturn(':dcValue1');
        $queryBuilder->method('expr')->willReturn($this->createMock(ExpressionBuilder::class));
        $queryBuilder->method('executeQuery')->willReturn($result);

        return $queryBuilder;
    }
}
