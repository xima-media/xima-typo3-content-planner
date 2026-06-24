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

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\CommentItem;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * CommentRepositoryTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class CommentRepositoryTest extends AbstractFunctionalTestCase
{
    private CommentRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');
        $this->subject = $this->get(CommentRepository::class);
    }

    #[Test]
    public function findAllByRecordReturnsRootCommentsSortedByActivity(): void
    {
        $result = $this->subject->findAllByRecord(10, 'pages');

        self::assertCount(2, $result);
        self::assertInstanceOf(CommentItem::class, $result[0]);
        // Comment B (uid 2) has a reply at crdate 3000 => highest last_activity, comes first DESC.
        self::assertSame(2, (int) $result[0]->data['uid']);
        self::assertSame(1, (int) $result[1]->data['uid']);
    }

    #[Test]
    public function findAllByRecordGroupsRepliesUnderRootComment(): void
    {
        $result = $this->subject->findAllByRecord(10, 'pages');

        $commentB = $result[0];
        self::assertSame(2, (int) $commentB->data['uid']);
        // Only the non-deleted reply (uid 3) should be present, deleted reply (uid 7) excluded.
        self::assertCount(1, $commentB->getReplies());
        self::assertSame(3, (int) $commentB->getReplies()[0]->data['uid']);
    }

    #[Test]
    public function findAllByRecordExcludesResolvedRootCommentsByDefault(): void
    {
        $result = $this->subject->findAllByRecord(10, 'pages');

        $uids = array_map(static fn (CommentItem $item): int => (int) $item->data['uid'], $result);
        self::assertNotContains(4, $uids);
    }

    #[Test]
    public function findAllByRecordIncludesResolvedRootCommentsWhenRequested(): void
    {
        $result = $this->subject->findAllByRecord(10, 'pages', false, 'DESC', true);

        $uids = array_map(static fn (CommentItem $item): int => (int) $item->data['uid'], $result);
        self::assertContains(4, $uids);
        self::assertCount(3, $result);
    }

    #[Test]
    public function findAllByRecordReturnsRawRowsWhenRawIsTrue(): void
    {
        $result = $this->subject->findAllByRecord(10, 'pages', true);

        self::assertCount(2, $result);
        self::assertIsArray($result[0]);
        self::assertArrayHasKey('last_activity', $result[0]);
    }

    #[Test]
    public function findAllByRecordRespectsAscendingSortDirection(): void
    {
        $result = $this->subject->findAllByRecord(10, 'pages', false, 'ASC');

        self::assertSame(1, (int) $result[0]->data['uid']);
        self::assertSame(2, (int) $result[1]->data['uid']);
    }

    #[Test]
    public function findAllByRecordReturnsEmptyArrayForUnknownRecord(): void
    {
        self::assertSame([], $this->subject->findAllByRecord(999, 'pages'));
    }

    #[Test]
    public function countAllByRecordCountsOpenCommentsByDefault(): void
    {
        // uid 1, 2, 3 are open and non-deleted for record 10 (resolved=4, deleted=5/7 excluded).
        self::assertSame(3, $this->subject->countAllByRecord(10, 'pages'));
    }

    #[Test]
    public function countAllByRecordCountsAllNonDeletedWhenCountAll(): void
    {
        // uid 1, 2, 3, 4 (resolved counts when countAll), deleted 5/7 excluded.
        self::assertSame(4, $this->subject->countAllByRecord(10, 'pages', true));
    }

    #[Test]
    public function countAllByRecordCountsOnlyResolvedWhenOnlyResolved(): void
    {
        self::assertSame(1, $this->subject->countAllByRecord(10, 'pages', false, true));
    }

    #[Test]
    public function countTodoAllByRecordSumsTotalTodos(): void
    {
        self::assertSame(3, $this->subject->countTodoAllByRecord(10, 'pages', 'todo_total'));
    }

    #[Test]
    public function countTodoAllByRecordSumsResolvedTodos(): void
    {
        self::assertSame(1, $this->subject->countTodoAllByRecord(10, 'pages', 'todo_resolved'));
    }

    #[Test]
    public function countTodoAllByRecordCountsAllRecordsWhenAllRecordsTrue(): void
    {
        self::assertSame(3, $this->subject->countTodoAllByRecord(null, null, 'todo_total', true));
    }

    #[Test]
    public function countTodoAllByRecordThrowsForInvalidField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->subject->countTodoAllByRecord(10, 'pages', 'invalid_field');
    }

    #[Test]
    public function findSingleTodoCommentUidReturnsUidWhenExactlyOneHasTodos(): void
    {
        // Only comment uid 2 has todo_total > 0 for record 10.
        self::assertSame(2, $this->subject->findSingleTodoCommentUid(10, 'pages'));
    }

    #[Test]
    public function findSingleTodoCommentUidReturnsNullWhenNoneHaveTodos(): void
    {
        self::assertNull($this->subject->findSingleTodoCommentUid(20, 'pages'));
    }

    #[Test]
    public function findByUidReturnsCommentRow(): void
    {
        $result = $this->subject->findByUid(1);

        self::assertIsArray($result);
        self::assertSame('Root comment A', $result['content']);
    }

    #[Test]
    public function findByUidReturnsFalseForZero(): void
    {
        self::assertFalse($this->subject->findByUid(0));
    }

    #[Test]
    public function findByUidReturnsFalseForDeletedComment(): void
    {
        self::assertFalse($this->subject->findByUid(5));
    }

    #[Test]
    public function deleteRepliesByParentUidSoftDeletesReplies(): void
    {
        $this->subject->deleteRepliesByParentUid(2);

        self::assertFalse($this->subject->findByUid(3));
    }

    #[Test]
    public function deleteAllCommentsByRecordSoftDeletesAllCommentsOfRecord(): void
    {
        $this->subject->deleteAllCommentsByRecord(10, 'pages');

        self::assertSame(0, $this->subject->countAllByRecord(10, 'pages', true));
    }

    #[Test]
    public function deleteAllCommentsByRecordWithLikeOnlyDeletesMatching(): void
    {
        $this->subject->deleteAllCommentsByRecord(10, 'pages', 'Root comment A');

        self::assertFalse($this->subject->findByUid(1));
        self::assertIsArray($this->subject->findByUid(2));
    }
}
