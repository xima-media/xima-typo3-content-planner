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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Domain\Repository;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Domain\Model\{Notification, NotificationEventType, NotificationReason};
use Xima\XimaTypo3ContentPlanner\Domain\Repository\ImmediateEmailQueueRepository;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * ImmediateEmailQueueRepositoryTest.
 *
 * Covers the persistence layer backing {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\Immediate\ImmediateEmailService}
 * (issue #306): every incoming immediate-mode notification is enqueued, `findLastSentAt()`
 * reports the throttle window's anchor per `(backend_user, tablename, record_uid)`, and
 * `markSentByUids()` is scoped exactly like {@see \Xima\XimaTypo3ContentPlanner\Domain\Repository\NotificationRepository::markDigestedByUids()}.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ImmediateEmailQueueRepositoryTest extends AbstractFunctionalTestCase
{
    private ImmediateEmailQueueRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->get(ImmediateEmailQueueRepository::class);
    }

    #[Test]
    public function enqueuePersistsAQueueRowWithNoSentAtYet(): void
    {
        $this->subject->enqueue($this->buildNotification(recipientUid: 2, table: 'pages', recordUid: 1, crdate: 1000));

        $rows = $this->subject->findPending(2, 'pages', 1);

        self::assertCount(1, $rows);
        self::assertNull($rows[0]['sent_at']);
        self::assertSame(1000, (int) $rows[0]['crdate']);
    }

    #[Test]
    public function findLastSentAtIsNullWhenNothingWasEverSent(): void
    {
        $this->subject->enqueue($this->buildNotification(recipientUid: 2, table: 'pages', recordUid: 1, crdate: 1000));

        self::assertNull($this->subject->findLastSentAt(2, 'pages', 1));
    }

    #[Test]
    public function findLastSentAtReturnsTheMostRecentSentAtForThatRecipientAndRecord(): void
    {
        $this->subject->enqueue($this->buildNotification(recipientUid: 2, table: 'pages', recordUid: 1, crdate: 1000));
        $this->subject->enqueue($this->buildNotification(recipientUid: 2, table: 'pages', recordUid: 1, crdate: 1001));
        $uids = array_map(static fn (array $row): int => (int) $row['uid'], $this->subject->findPending(2, 'pages', 1));
        $this->subject->markSentByUids($uids, 5000);

        self::assertSame(5000, $this->subject->findLastSentAt(2, 'pages', 1));
    }

    #[Test]
    public function findLastSentAtIsScopedPerRecipientTableAndRecord(): void
    {
        $this->subject->enqueue($this->buildNotification(recipientUid: 2, table: 'pages', recordUid: 1, crdate: 1000));
        $uids = array_map(static fn (array $row): int => (int) $row['uid'], $this->subject->findPending(2, 'pages', 1));
        $this->subject->markSentByUids($uids, 5000);

        self::assertNull($this->subject->findLastSentAt(3, 'pages', 1), 'different recipient');
        self::assertNull($this->subject->findLastSentAt(2, 'pages', 2), 'different record');
        self::assertNull($this->subject->findLastSentAt(2, 'tt_content', 1), 'different table');
    }

    #[Test]
    public function findPendingOnlyReturnsRowsNotYetSentOrderedChronologically(): void
    {
        $this->subject->enqueue($this->buildNotification(recipientUid: 2, table: 'pages', recordUid: 1, crdate: 1002));
        $this->subject->enqueue($this->buildNotification(recipientUid: 2, table: 'pages', recordUid: 1, crdate: 1000));
        $this->subject->enqueue($this->buildNotification(recipientUid: 2, table: 'pages', recordUid: 1, crdate: 1001));

        $rows = $this->subject->findPending(2, 'pages', 1);

        self::assertSame([1000, 1001, 1002], array_map(static fn (array $row): int => (int) $row['crdate'], $rows));
    }

    #[Test]
    public function markSentByUidsOnlyTouchesTheGivenUids(): void
    {
        $this->subject->enqueue($this->buildNotification(recipientUid: 2, table: 'pages', recordUid: 1, crdate: 1000));
        $this->subject->enqueue($this->buildNotification(recipientUid: 2, table: 'pages', recordUid: 1, crdate: 1001));
        $rows = $this->subject->findPending(2, 'pages', 1);

        $this->subject->markSentByUids([(int) $rows[0]['uid']], 5000);

        $stillPending = $this->subject->findPending(2, 'pages', 1);
        self::assertCount(1, $stillPending);
        self::assertSame(1001, (int) $stillPending[0]['crdate']);
    }

    #[Test]
    public function markSentByUidsWithAnEmptyListIsANoop(): void
    {
        $this->subject->enqueue($this->buildNotification(recipientUid: 2, table: 'pages', recordUid: 1, crdate: 1000));

        $this->subject->markSentByUids([], 5000);

        self::assertCount(1, $this->subject->findPending(2, 'pages', 1));
    }

    private function buildNotification(int $recipientUid, string $table, int $recordUid, int $crdate): Notification
    {
        return new Notification(
            $recipientUid,
            NotificationEventType::StatusChanged,
            $table,
            $recordUid,
            1,
            NotificationReason::WatchingManually,
            ['version' => 1, 'title' => 'Home', 'previousStatus' => null, 'newStatus' => 'Draft'],
            $crdate,
        );
    }
}
