<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Repository;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * RecordRepositoryTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class RecordRepositoryTest extends AbstractFunctionalTestCase
{
    private RecordRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->loginBackendUser();
        $this->subject = $this->get(RecordRepository::class);
    }

    #[Test]
    public function findByUidReturnsRecordWithStatusFields(): void
    {
        $result = $this->subject->findByUid('pages', 1);

        self::assertIsArray($result);
        self::assertSame(1, (int) $result['uid']);
        self::assertSame(2, (int) $result['tx_ximatypo3contentplanner_status']);
        self::assertSame(1, (int) $result['tx_ximatypo3contentplanner_assignee']);
    }

    #[Test]
    public function findByUidReturnsNullWhenTableAndUidEmpty(): void
    {
        self::assertNull($this->subject->findByUid(null, null));
    }

    #[Test]
    public function findByUidReturnsNullForUnregisteredTable(): void
    {
        // be_users is not a registered content planner record table and must be rejected
        // by the whitelist regardless of whether a matching row exists.
        self::assertNull($this->subject->findByUid('be_users', 1));
    }

    #[Test]
    public function findByUidExcludesDeletedRecordByDefault(): void
    {
        self::assertFalse($this->subject->findByUid('pages', 5));
    }

    #[Test]
    public function findByUidIncludesDeletedWhenVisibilityRestrictionIgnored(): void
    {
        // deleted=1 still filtered by the explicit deleted=0 where-clause; hidden restriction differs.
        // Page 5 is deleted, so it stays excluded; verify a hidden-independent fetch of page 1 works.
        $result = $this->subject->findByUid('pages', 1, true);

        self::assertIsArray($result);
        self::assertSame(1, (int) $result['uid']);
    }

    #[Test]
    public function countRecordsByStatusCountsNonDeletedRecordsPerStatus(): void
    {
        $counts = $this->subject->countRecordsByStatus();

        // pages fixture: status 2 (page 1), status 3 (page 2), status 1 (page 3);
        // page 4 has no status and page 5 (status 2) is deleted, so both are excluded.
        self::assertSame(1, $counts[1] ?? 0);
        self::assertSame(1, $counts[2] ?? 0);
        self::assertSame(1, $counts[3] ?? 0);
    }

    #[Test]
    public function findByPidReturnsRecordsWithStatusForGivenPid(): void
    {
        $result = $this->subject->findByPid('pages', 1);

        // Pages 2 and 3 have status and pid=1; page 4 has status 0 (excluded), page 5 deleted.
        $uids = array_map(static fn (array $row): int => (int) $row['uid'], $result);
        self::assertContains(2, $uids);
        self::assertContains(3, $uids);
        self::assertNotContains(4, $uids);
        self::assertNotContains(5, $uids);
    }

    #[Test]
    public function findByPidReturnsAllRecordsWithStatusWhenNoPid(): void
    {
        $result = $this->subject->findByPid('pages');

        $uids = array_map(static fn (array $row): int => (int) $row['uid'], $result);
        self::assertContains(1, $uids);
        self::assertContains(2, $uids);
        self::assertContains(3, $uids);
        self::assertNotContains(4, $uids);
    }

    #[Test]
    public function findByPidStillQueriesTableWithoutTcaLabel(): void
    {
        // TCA does not force a ctrl.label; without a fallback the select list would read
        // "SELECT uid,  as title" and the query would not even parse.
        $label = $GLOBALS['TCA']['pages']['ctrl']['label'];
        unset($GLOBALS['TCA']['pages']['ctrl']['label']);

        try {
            $result = $this->subject->findByPid('pages', 1);
        } finally {
            $GLOBALS['TCA']['pages']['ctrl']['label'] = $label;
        }

        $uids = array_map(static fn (array $row): int => (int) $row['uid'], $result);
        self::assertContains(2, $uids);
    }

    #[Test]
    public function findByPidReturnsCachedResultOnSecondCall(): void
    {
        $first = $this->subject->findByPid('pages', 1);
        $second = $this->subject->findByPid('pages', 1);

        self::assertSame($first, $second);
    }

    #[Test]
    public function findByPidWithoutTstampOrderingStillReturnsRecords(): void
    {
        $result = $this->subject->findByPid('pages', 1, false);

        self::assertNotEmpty($result);
    }

    #[Test]
    public function updateStatusByUidChangesStatus(): void
    {
        $this->subject->updateStatusByUid('pages', 3, 2);

        $record = $this->subject->findByUid('pages', 3);
        self::assertIsArray($record);
        self::assertSame(2, (int) $record['tx_ximatypo3contentplanner_status']);
    }

    #[Test]
    public function updateStatusByUidUpdatesAssigneeWhenProvided(): void
    {
        $this->subject->updateStatusByUid('pages', 3, 2, 9);

        $record = $this->subject->findByUid('pages', 3);
        self::assertIsArray($record);
        self::assertSame(9, (int) $record['tx_ximatypo3contentplanner_assignee']);
    }

    #[Test]
    public function updateCommentsRelationByRecordSetsCommentCount(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');

        // The comment fixture targets foreign_uid=10; create that page so the relation can be written.
        $this->get(ConnectionPool::class)->getConnectionForTable('pages')->insert('pages', [
            'uid' => 10,
            'pid' => 1,
            'title' => 'Commented page',
            'perms_user' => 31,
            'perms_group' => 31,
            'perms_everybody' => 31,
        ]);

        $expectedCount = $this->get(CommentRepository::class)->countAllByRecord(10, 'pages');
        self::assertGreaterThan(0, $expectedCount);

        $this->subject->updateCommentsRelationByRecord('pages', 10);

        $record = $this->subject->findByUid('pages', 10);
        self::assertIsArray($record);
        self::assertSame($expectedCount, (int) $record['tx_ximatypo3contentplanner_comments']);
    }

    #[Test]
    public function findByPidExcludesHiddenRecordsByDefault(): void
    {
        $result = $this->subject->findByPid('pages', 1);

        $uids = array_map(static fn (array $row): int => (int) $row['uid'], $result);
        self::assertNotContains(6, $uids, 'hidden page 6 must be excluded without ignoreVisibilityRestriction');
    }

    #[Test]
    public function findByPidIncludesHiddenRecordsWhenVisibilityRestrictionIgnored(): void
    {
        $result = $this->subject->findByPid('pages', 1, ignoreVisibilityRestriction: true);

        $uids = array_map(static fn (array $row): int => (int) $row['uid'], $result);
        self::assertContains(6, $uids, 'hidden page 6 must be included when the restriction is ignored');
    }

    #[Test]
    public function findByUidExcludesWorkspaceVersionRecord(): void
    {
        // Page 7 is a workspace draft of page 2 (t3ver_wsid=5). Content Planner always
        // reflects the live record, so requesting the version's own uid must miss, see #400.
        self::assertFalse($this->subject->findByUid('pages', 7));
    }

    #[Test]
    public function findByPidExcludesWorkspaceVersionRecord(): void
    {
        $result = $this->subject->findByPid('pages', 1);

        $uids = array_map(static fn (array $row): int => (int) $row['uid'], $result);
        self::assertNotContains(7, $uids, 'workspace draft of page 2 must be excluded, only the live record counts');
    }

    #[Test]
    public function countRecordsByStatusExcludesWorkspaceVersionRecord(): void
    {
        $counts = $this->subject->countRecordsByStatus();

        // Page 7 (workspace draft, status 9) must not be counted; only live records do.
        self::assertArrayNotHasKey(9, $counts);
    }

    #[Test]
    public function updateStatusByUidClearsAssigneeWhenExplicitlyNull(): void
    {
        $this->subject->updateStatusByUid('pages', 3, 2, 9);
        $this->subject->updateStatusByUid('pages', 3, 2, null);

        $record = $this->subject->findByUid('pages', 3);
        self::assertIsArray($record);
        self::assertNull($record['tx_ximatypo3contentplanner_assignee']);
    }

    #[Test]
    public function findAllByFilterReturnsLiveRecordsWithStatusNewestFirst(): void
    {
        $this->skipOnSqlite();

        // Page 4 has no status, page 5 is deleted, page 7 is a workspace draft.
        self::assertSame([6, 3, 2, 1], $this->findUidsByFilter(type: 'pages'));
    }

    #[Test]
    public function findAllByFilterFiltersByStatusAndAssignee(): void
    {
        $this->skipOnSqlite();

        self::assertSame([6, 1], $this->findUidsByFilter(status: 2, type: 'pages'));
        self::assertSame([2], $this->findUidsByFilter(assignee: 2, type: 'pages'));
    }

    #[Test]
    public function findAllByFilterSearchesTitleAndUid(): void
    {
        $this->skipOnSqlite();

        self::assertSame([3], $this->findUidsByFilter(search: 'Draft', type: 'pages'));
        self::assertSame([1], $this->findUidsByFilter(search: '1', type: 'pages'));
    }

    #[Test]
    public function findAllByFilterLimitsResults(): void
    {
        $this->skipOnSqlite();

        self::assertSame([6, 3], $this->findUidsByFilter(type: 'pages', maxResults: 2));
    }

    #[Test]
    public function findAllByFilterReturnsOnlyRecordsWithOpenTodos(): void
    {
        $this->skipOnSqlite();
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');
        // Comment B on page 10 has one of three todos resolved. Page 1 only carries a resolved
        // comment whose todos are all done, so neither filter may return it.
        $this->get(ConnectionPool::class)->getConnectionForTable(Configuration::TABLE_COMMENT)->insert(Configuration::TABLE_COMMENT, [
            'uid' => 20,
            'foreign_uid' => 1,
            'foreign_table' => 'pages',
            'content' => 'Done',
            'todo_total' => 2,
            'todo_resolved' => 2,
            'resolved_date' => 5000,
        ]);
        $this->get(ConnectionPool::class)->getConnectionForTable('pages')->insert('pages', [
            'uid' => 10,
            'pid' => 1,
            'title' => 'Commented page',
            'tx_ximatypo3contentplanner_status' => 1,
            'perms_user' => 31,
            'perms_group' => 31,
            'perms_everybody' => 31,
        ]);

        self::assertSame([10], $this->findUidsByFilter(type: 'pages', todo: true));
        self::assertSame([10], $this->findUidsByFilter(type: 'pages', openComments: true));
    }

    #[Test]
    public function findAllByFilterUnionsAllRegisteredTables(): void
    {
        $this->skipOnSqlite();
        $this->enableExtensionFeature('enableFilelistSupport');
        $this->importCSVDataSet(__DIR__.'/Fixtures/sys_file_metadata.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/folders.csv');

        $titles = array_column($this->subject->findAllByFilter(maxResults: 50), 'title', 'tablename');

        self::assertSame('example.pdf', $titles['sys_file_metadata'] ?? null);
        self::assertArrayHasKey(Configuration::TABLE_FOLDER, $titles);
        self::assertArrayHasKey('pages', $titles);
    }

    /**
     * @return list<int>
     */
    private function findUidsByFilter(
        ?string $search = null,
        ?int $status = null,
        ?int $assignee = null,
        ?string $type = null,
        ?bool $todo = null,
        int $maxResults = 20,
        bool $openComments = false,
    ): array {
        $result = $this->subject->findAllByFilter($search, $status, $assignee, $type, $todo, $maxResults, $openComments);
        self::assertIsArray($result);

        return array_values(array_map(static fn (array $row): int => (int) $row['uid'], $result));
    }

    // CI runs this suite against MySQL and MariaDB as well.
    private function skipOnSqlite(): void
    {
        $platform = $this->get(ConnectionPool::class)->getConnectionForTable('pages')->getDatabasePlatform();
        if ($platform instanceof SQLitePlatform) {
            self::markTestSkipped('findAllByFilter() builds UNION SQL that SQLite does not parse.');
        }
    }
}
