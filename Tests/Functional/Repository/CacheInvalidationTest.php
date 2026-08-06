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
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * CacheInvalidationTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class CacheInvalidationTest extends AbstractFunctionalTestCase
{
    /*
     * findByPid() is the only record read that goes through the cache frontend, so it is
     * the only place a mutation can leave a stale row behind. Each case warms that cache,
     * mutates through one path, then asserts the next read reflects the change. Both
     * updateStatusByUid and updateCommentsRelationByRecord failed here before they
     * started invalidating.
     */

    private RecordRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');
        $this->loginBackendUser();
        $this->subject = $this->get(RecordRepository::class);
    }

    #[Test]
    public function updateStatusByUidIsVisibleToTheNextCachedRead(): void
    {
        $before = $this->statusOfPage(3);
        self::assertSame(1, $before);

        $this->subject->updateStatusByUid('pages', 3, 2);

        self::assertSame(2, $this->statusOfPage(3), 'findByPid() served a stale status');
    }

    #[Test]
    public function updateCommentsRelationIsVisibleToTheNextCachedRead(): void
    {
        // Warm the cache while page 2 still reports its fixture comment count.
        $before = $this->commentCountOfPage(2);

        // Page 2 has no comment rows in the fixture, so recounting must land on 0.
        $this->subject->updateCommentsRelationByRecord('pages', 2);

        self::assertNotSame($before, $this->commentCountOfPage(2), 'findByPid() served a stale comment count');
        self::assertSame(0, $this->commentCountOfPage(2));
    }

    #[Test]
    public function deletingAllCommentsOfARecordIsVisibleToTheNextCachedRead(): void
    {
        // Page 10 owns the comment rows in the fixture, so give them to page 2 first —
        // otherwise the count is 0 before and after and the test proves nothing.
        $this->getConnectionPool()->getConnectionForTable(Configuration::TABLE_COMMENT)->update(
            Configuration::TABLE_COMMENT,
            ['foreign_uid' => 2],
            ['foreign_uid' => 10, 'foreign_table' => 'pages'],
        );
        $this->subject->updateCommentsRelationByRecord('pages', 2);
        self::assertGreaterThan(0, $this->commentCountOfPage(2), 'fixture must give page 2 comments to begin with');

        $this->get(CommentRepository::class)->deleteAllCommentsByRecord(2, 'pages');
        $this->subject->updateCommentsRelationByRecord('pages', 2);

        self::assertSame(0, $this->commentCountOfPage(2), 'findByPid() served a stale comment count');
    }

    #[Test]
    public function gainingAStatusIsVisibleToTheNextCachedRead(): void
    {
        // findByPid() excludes records without a status, so page 4 is in no warmed entry
        // and has no per-row tag. Only the table-wide tag can reach the entry.
        self::assertNull($this->statusOfPage(4, allowMissing: true));

        $this->subject->updateStatusByUid('pages', 4, 3);

        self::assertSame(3, $this->statusOfPage(4), 'findByPid() omitted a newly active record');
    }

    private function statusOfPage(int $uid, bool $allowMissing = false): ?int
    {
        foreach ($this->subject->findByPid('pages', 1) as $row) {
            if ((int) $row['uid'] === $uid) {
                return null === $row[Configuration::FIELD_STATUS] ? null : (int) $row[Configuration::FIELD_STATUS];
            }
        }

        // A record without a status is legitimately absent — findByPid() filters those out.
        if ($allowMissing) {
            return null;
        }

        self::fail("page $uid not returned by findByPid()");
    }

    private function commentCountOfPage(int $uid): int
    {
        foreach ($this->subject->findByPid('pages', 1) as $row) {
            if ((int) $row['uid'] === $uid) {
                return (int) $row[Configuration::FIELD_COMMENTS];
            }
        }

        self::fail("page $uid not returned by findByPid()");
    }
}
