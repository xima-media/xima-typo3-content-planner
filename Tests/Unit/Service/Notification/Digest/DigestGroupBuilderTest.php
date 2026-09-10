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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Service\Notification\Digest;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Service\Notification\Digest\DigestGroupBuilder;

/**
 * DigestGroupBuilderTest.
 *
 * Covers the pure dedup/grouping logic behind issue #302's email digest - no database involved,
 * see {@see \Xima\XimaTypo3ContentPlanner\Tests\Functional\Command\EmailDigestCommandTest} for the
 * end-to-end functional coverage the issue's acceptance criteria explicitly calls for.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class DigestGroupBuilderTest extends TestCase
{
    private DigestGroupBuilder $subject;

    protected function setUp(): void
    {
        $this->subject = new DigestGroupBuilder();
    }

    #[Test]
    public function fiveStatusChangesOnOneRecordCollapseToOneGroupWithADedupedChain(): void
    {
        $rows = [
            $this->statusRow(crdate: 1000, previous: null, new: 'Draft'),
            $this->statusRow(crdate: 1001, previous: 'Draft', new: 'Review'),
            $this->statusRow(crdate: 1002, previous: 'Review', new: 'Review'),
            $this->statusRow(crdate: 1003, previous: 'Review', new: 'Review'),
            $this->statusRow(crdate: 1004, previous: 'Review', new: 'Approved'),
        ];

        $groups = $this->subject->build($rows, static fn (mixed $v): string => (string) $v);

        self::assertCount(1, $groups);
        self::assertSame(['Draft', 'Review', 'Approved'], $groups[0]->getStatusChain());
        self::assertSame(5, $groups[0]->getEventCount());
    }

    #[Test]
    public function groupsAreKeyedByTableAndRecordUid(): void
    {
        $rows = [
            $this->statusRow(crdate: 1000, previous: null, new: 'Draft', recordUid: 1),
            $this->statusRow(crdate: 1001, previous: null, new: 'Draft', recordUid: 2),
            $this->statusRow(crdate: 1002, previous: null, new: 'Draft', table: 'tt_content', recordUid: 1),
        ];

        $groups = $this->subject->build($rows, static fn (mixed $v): string => (string) $v);

        self::assertCount(3, $groups);
    }

    #[Test]
    public function rowsAreSortedChronologicallyRegardlessOfInputOrder(): void
    {
        $rows = [
            $this->statusRow(crdate: 2000, previous: 'Review', new: 'Approved'),
            $this->statusRow(crdate: 1000, previous: null, new: 'Draft'),
            $this->statusRow(crdate: 1500, previous: 'Draft', new: 'Review'),
        ];

        $groups = $this->subject->build($rows, static fn (mixed $v): string => (string) $v);

        self::assertSame(['Draft', 'Review', 'Approved'], $groups[0]->getStatusChain());
    }

    #[Test]
    public function assigneeChainUsesTheInjectedLabelResolver(): void
    {
        $rows = [
            $this->assigneeRow(crdate: 1000, previous: 1, new: 2),
            $this->assigneeRow(crdate: 1001, previous: 2, new: 5),
        ];

        $groups = $this->subject->build($rows, static fn (mixed $v): string => null === $v ? 'Unassigned' : 'user-'.$v);

        self::assertSame(['user-1', 'user-2', 'user-5'], $groups[0]->getAssigneeChain());
    }

    #[Test]
    public function assigneeChainStartsWithUnassignedLabelWhenFirstEventHadNoPreviousAssignee(): void
    {
        $rows = [$this->assigneeRow(crdate: 1000, previous: null, new: 2)];

        $groups = $this->subject->build($rows, static fn (mixed $v): string => null === $v ? 'Unassigned' : 'user-'.$v);

        self::assertSame(['Unassigned', 'user-2'], $groups[0]->getAssigneeChain());
    }

    #[Test]
    public function commentEventsExposeTheLatestExcerptAndTheirOwnCount(): void
    {
        $rows = [
            $this->commentRow(crdate: 1000, excerpt: 'First comment'),
            $this->commentRow(crdate: 1001, excerpt: 'Second comment'),
        ];

        $groups = $this->subject->build($rows, static fn (mixed $v): string => (string) $v);

        self::assertSame('Second comment', $groups[0]->getLatestCommentExcerpt());
        self::assertSame(2, $groups[0]->getCommentCount());
        self::assertSame(2, $groups[0]->getEventCount());
    }

    #[Test]
    public function mixedEventTypesOnOneRecordProduceOneGroupWithBothChains(): void
    {
        $rows = [
            $this->statusRow(crdate: 1000, previous: null, new: 'Draft'),
            $this->assigneeRow(crdate: 1001, previous: null, new: 3),
            $this->commentRow(crdate: 1002, excerpt: 'Looks good'),
        ];

        $groups = $this->subject->build($rows, static fn (mixed $v): string => null === $v ? 'Unassigned' : 'user-'.$v);

        self::assertCount(1, $groups);
        self::assertSame(['Draft'], $groups[0]->getStatusChain());
        self::assertSame(['Unassigned', 'user-3'], $groups[0]->getAssigneeChain());
        self::assertSame('Looks good', $groups[0]->getLatestCommentExcerpt());
        self::assertSame(3, $groups[0]->getEventCount());
    }

    #[Test]
    public function theGroupCarriesTheLatestNotificationsTitleAndReason(): void
    {
        $rows = [
            $this->statusRow(crdate: 1000, previous: null, new: 'Draft', title: 'Old title', reason: 'watching_manually'),
            $this->statusRow(crdate: 1001, previous: 'Draft', new: 'Review', title: 'New title', reason: 'watching_since_status_change'),
        ];

        $groups = $this->subject->build($rows, static fn (mixed $v): string => (string) $v);

        self::assertSame('New title', $groups[0]->getTitle());
        self::assertSame('watching_since_status_change', $groups[0]->getReason());
    }

    #[Test]
    public function contentChangeRowsSumTheChangeCountAndUnionTheDistinctActors(): void
    {
        // Two separate rows (e.g. across two digest-eligible days), each already aggregated at
        // write time by NotificationRepository::upsertContentChange() - the digest sums across them.
        $rows = [
            $this->contentChangeRow(crdate: 1000, changeCount: 9, actorUids: [2, 3]),
            $this->contentChangeRow(crdate: 2000, changeCount: 5, actorUids: [3]),
        ];

        $groups = $this->subject->build($rows, static fn (mixed $v): string => (string) $v);

        self::assertCount(1, $groups);
        self::assertSame(14, $groups[0]->getContentChangeCount());
        self::assertSame(2, $groups[0]->getContentChangeActorCount());
    }

    #[Test]
    public function contentChangeCountIsZeroWhenThereAreNoContentChangeEvents(): void
    {
        $groups = $this->subject->build([$this->statusRow(crdate: 1000, previous: null, new: 'Draft')], static fn (mixed $v): string => (string) $v);

        self::assertSame(0, $groups[0]->getContentChangeCount());
        self::assertSame(0, $groups[0]->getContentChangeActorCount());
    }

    /**
     * @return array<string, mixed>
     */
    private function contentChangeRow(int $crdate, int $changeCount, array $actorUids, string $table = 'pages', int $recordUid = 1): array
    {
        return [
            'tablename' => $table,
            'record_uid' => $recordUid,
            'event_type' => 'content_changed',
            'reason' => 'watching_manually',
            'crdate' => $crdate,
            'payload' => ['version' => 1, 'title' => 'Home', 'changeCount' => $changeCount, 'actorUids' => $actorUids],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function statusRow(int $crdate, ?string $previous, string $new, string $table = 'pages', int $recordUid = 1, string $title = 'Home', string $reason = 'watching_manually'): array
    {
        return [
            'tablename' => $table,
            'record_uid' => $recordUid,
            'event_type' => 'status_changed',
            'reason' => $reason,
            'crdate' => $crdate,
            'payload' => ['version' => 1, 'title' => $title, 'previousStatus' => $previous, 'newStatus' => $new],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function assigneeRow(int $crdate, ?int $previous, ?int $new, string $table = 'pages', int $recordUid = 1): array
    {
        return [
            'tablename' => $table,
            'record_uid' => $recordUid,
            'event_type' => 'assigned',
            'reason' => 'watching_manually',
            'crdate' => $crdate,
            'payload' => ['version' => 1, 'title' => 'Home', 'previousAssignee' => $previous, 'newAssignee' => $new],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function commentRow(int $crdate, string $excerpt, string $table = 'pages', int $recordUid = 1): array
    {
        return [
            'tablename' => $table,
            'record_uid' => $recordUid,
            'event_type' => 'comment_added',
            'reason' => 'watching_manually',
            'crdate' => $crdate,
            'payload' => ['version' => 1, 'title' => 'Home', 'commentExcerpt' => $excerpt],
        ];
    }
}
