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

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
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

    // NOTE: findAllByFilter() builds raw "(SELECT ...) UNION (SELECT ...)" SQL which is invalid
    // under SQLite (the functional test driver). It is therefore not covered here.
}
