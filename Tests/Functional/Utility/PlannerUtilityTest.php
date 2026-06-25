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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Utility;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility;

use function count;

/**
 * PlannerUtilityTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class PlannerUtilityTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');
        $this->loginBackendUser();
        $this->setUpBackendRequest();
    }

    #[Test]
    public function getListOfStatusReturnsAllStatusEntities(): void
    {
        $list = PlannerUtility::getListOfStatus();

        self::assertCount(3, $list);
        self::assertContainsOnlyInstancesOf(Status::class, $list);
    }

    #[Test]
    public function getStatusByStringResolvesByTitle(): void
    {
        $status = PlannerUtility::getStatus('In Progress');

        self::assertInstanceOf(Status::class, $status);
        self::assertSame(2, $status->getUid());
    }

    #[Test]
    public function getStatusByIntResolvesByUid(): void
    {
        $status = PlannerUtility::getStatus(3);

        self::assertInstanceOf(Status::class, $status);
        self::assertSame('Done', $status->getTitle());
    }

    #[Test]
    public function getStatusReturnsNullForUnknownTitle(): void
    {
        self::assertNull(PlannerUtility::getStatus('Nonexistent'));
    }

    #[Test]
    public function getStatusOfRecordReturnsAssignedStatus(): void
    {
        $status = PlannerUtility::getStatusOfRecord('pages', 1);

        self::assertInstanceOf(Status::class, $status);
        self::assertSame(2, $status->getUid());
    }

    #[Test]
    public function getStatusOfRecordReturnsNullWhenNoStatus(): void
    {
        self::assertNull(PlannerUtility::getStatusOfRecord('pages', 2));
    }

    #[Test]
    public function updateStatusForRecordWithStatusEntity(): void
    {
        $statusEntity = $this->get(StatusRepository::class)->findByUid(3);
        PlannerUtility::updateStatusForRecord('pages', 2, $statusEntity);

        $record = $this->get(RecordRepository::class)->findByUid('pages', 2);
        self::assertSame(3, (int) $record[Configuration::FIELD_STATUS]);
    }

    #[Test]
    public function updateStatusForRecordWithStatusTitleAndAssigneeUsername(): void
    {
        PlannerUtility::updateStatusForRecord('pages', 2, 'Done', 'admin');

        $record = $this->get(RecordRepository::class)->findByUid('pages', 2);
        self::assertSame(3, (int) $record[Configuration::FIELD_STATUS]);
        self::assertSame(1, (int) $record[Configuration::FIELD_ASSIGNEE]);
    }

    #[Test]
    public function updateStatusForRecordWithIntegerStatusId(): void
    {
        PlannerUtility::updateStatusForRecord('pages', 2, 1);

        $record = $this->get(RecordRepository::class)->findByUid('pages', 2);
        self::assertSame(1, (int) $record[Configuration::FIELD_STATUS]);
    }

    #[Test]
    public function updateStatusForRecordThrowsOnZeroStatusId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(9220772840);

        PlannerUtility::updateStatusForRecord('pages', 2, 0);
    }

    #[Test]
    public function updateStatusForRecordThrowsOnUnregisteredTable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(9518991865);

        PlannerUtility::updateStatusForRecord('be_users', 1, 1);
    }

    #[Test]
    public function updateStatusForRecordThrowsOnUnknownRecord(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(4064696674);

        PlannerUtility::updateStatusForRecord('pages', 999, 1);
    }

    #[Test]
    public function getCommentsOfRecordReturnsComments(): void
    {
        $comments = PlannerUtility::getCommentsOfRecord('pages', 1);

        self::assertCount(1, $comments);
    }

    #[Test]
    public function getCommentsOfRecordRawReturnsArrays(): void
    {
        $comments = PlannerUtility::getCommentsOfRecord('pages', 1, true);

        self::assertCount(1, $comments);
        self::assertIsArray($comments[0]);
    }

    #[Test]
    public function addCommentsToRecordWithStringAndUsernameAuthor(): void
    {
        PlannerUtility::addCommentsToRecord('pages', 1, 'A brand new comment', 'admin');

        $count = $this->get(CommentRepository::class)->countAllByRecord(1, 'pages');
        self::assertSame(2, $count);
    }

    #[Test]
    public function addCommentsToRecordWithMultipleCommentsAndIntegerAuthor(): void
    {
        PlannerUtility::addCommentsToRecord('pages', 1, ['First', 'Second'], 1);

        $count = $this->get(CommentRepository::class)->countAllByRecord(1, 'pages');
        self::assertSame(3, $count);
    }

    #[Test]
    public function addCommentsToRecordThrowsOnInvalidAuthor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(4723563571);

        PlannerUtility::addCommentsToRecord('pages', 1, 'Comment', 0);
    }

    #[Test]
    public function clearCommentsOfRecordRemovesAllComments(): void
    {
        PlannerUtility::clearCommentsOfRecord('pages', 1);

        $count = $this->get(CommentRepository::class)->countAllByRecord(1, 'pages');
        self::assertSame(0, $count);
    }

    #[Test]
    public function generateTodoForCommentBuildsHtmlListAndEscapes(): void
    {
        $html = PlannerUtility::generateTodoForComment(['Plain todo', '<script>x</script>']);

        self::assertStringContainsString('<ul class="todo-list">', $html);
        self::assertStringContainsString('Plain todo', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertSame(2, substr_count($html, '<li>'));
    }

    #[Test]
    public function hasCommentsReturnsTrueWhenPositiveCount(): void
    {
        self::assertTrue(PlannerUtility::hasComments([Configuration::FIELD_COMMENTS => 3]));
    }

    #[Test]
    public function hasCommentsReturnsFalseWhenZeroOrMissing(): void
    {
        self::assertFalse(PlannerUtility::hasComments([Configuration::FIELD_COMMENTS => 0]));
        self::assertFalse(PlannerUtility::hasComments([]));
    }

    #[Test]
    public function getListOfStatusMatchesFixtureCount(): void
    {
        self::assertSame(3, count(PlannerUtility::getListOfStatus()));
    }
}
