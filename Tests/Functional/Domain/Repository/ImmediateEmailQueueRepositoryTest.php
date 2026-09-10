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
 * `claimPending()`/`markSent()` together provide the exact-claimed-set, lease-reclaimable
 * arbitration two concurrent callers need.
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
        $this->subject->markSent($uids, 5000);

        self::assertSame(5000, $this->subject->findLastSentAt(2, 'pages', 1));
    }

    #[Test]
    public function findLastSentAtIsScopedPerRecipientTableAndRecord(): void
    {
        $this->subject->enqueue($this->buildNotification(recipientUid: 2, table: 'pages', recordUid: 1, crdate: 1000));
        $uids = array_map(static fn (array $row): int => (int) $row['uid'], $this->subject->findPending(2, 'pages', 1));
        $this->subject->markSent($uids, 5000);

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
    public function markSentOnlyTouchesTheGivenUids(): void
    {
        $this->subject->enqueue($this->buildNotification(recipientUid: 2, table: 'pages', recordUid: 1, crdate: 1000));
        $this->subject->enqueue($this->buildNotification(recipientUid: 2, table: 'pages', recordUid: 1, crdate: 1001));
        $rows = $this->subject->findPending(2, 'pages', 1);

        $this->subject->markSent([(int) $rows[0]['uid']], 5000);

        $stillPending = $this->subject->findPending(2, 'pages', 1);
        self::assertCount(1, $stillPending);
        self::assertSame(1001, (int) $stillPending[0]['crdate']);
    }

    #[Test]
    public function markSentWithAnEmptyListIsANoop(): void
    {
        $this->subject->enqueue($this->buildNotification(recipientUid: 2, table: 'pages', recordUid: 1, crdate: 1000));

        $this->subject->markSent([], 5000);

        self::assertCount(1, $this->subject->findPending(2, 'pages', 1));
    }

    #[Test]
    public function claimPendingReturnsExactlyTheRowsItClaimed(): void
    {
        $this->subject->enqueue($this->buildNotification(recipientUid: 2, table: 'pages', recordUid: 1, crdate: 1000));
        $this->subject->enqueue($this->buildNotification(recipientUid: 2, table: 'pages', recordUid: 1, crdate: 1001));
        $uids = array_map(static fn (array $row): int => (int) $row['uid'], $this->subject->findPending(2, 'pages', 1));

        $claimed = $this->subject->claimPending($uids, time() - 300);

        self::assertCount(2, $claimed);
        self::assertSame($uids, array_map(static fn (array $row): int => (int) $row['uid'], $claimed));
    }

    #[Test]
    public function claimPendingWithAnEmptyListIsANoop(): void
    {
        self::assertSame([], $this->subject->claimPending([], time() - 300));
    }

    #[Test]
    public function claimPendingDoesNotReclaimAnActiveClaim(): void
    {
        $this->subject->enqueue($this->buildNotification(recipientUid: 2, table: 'pages', recordUid: 1, crdate: 1000));
        $uids = array_map(static fn (array $row): int => (int) $row['uid'], $this->subject->findPending(2, 'pages', 1));

        $first = $this->subject->claimPending($uids, time() - 300);
        $second = $this->subject->claimPending($uids, time() - 300);

        self::assertCount(1, $first);
        self::assertSame([], $second, 'a fresh, unexpired claim must not be reclaimable');
    }

    #[Test]
    public function claimPendingReclaimsAnExpiredClaim(): void
    {
        $this->subject->enqueue($this->buildNotification(recipientUid: 2, table: 'pages', recordUid: 1, crdate: 1000));
        $uids = array_map(static fn (array $row): int => (int) $row['uid'], $this->subject->findPending(2, 'pages', 1));

        $this->subject->claimPending($uids, time() - 300);
        // A lease expiry in the future means "anything claimed before right now is stale".
        $reclaimed = $this->subject->claimPending($uids, time() + 1);

        self::assertCount(1, $reclaimed, 'a claim older than the lease expiry must become reclaimable');
    }

    #[Test]
    public function claimPendingDoesNotClaimAlreadySentRows(): void
    {
        $this->subject->enqueue($this->buildNotification(recipientUid: 2, table: 'pages', recordUid: 1, crdate: 1000));
        $uids = array_map(static fn (array $row): int => (int) $row['uid'], $this->subject->findPending(2, 'pages', 1));
        $this->subject->markSent($uids, 5000);

        self::assertSame([], $this->subject->claimPending($uids, time() - 300));
    }

    #[Test]
    public function findDistinctPendingTriplesReturnsOnlyTriplesWithUnsentRows(): void
    {
        $this->subject->enqueue($this->buildNotification(recipientUid: 2, table: 'pages', recordUid: 1, crdate: 1000));
        $this->subject->enqueue($this->buildNotification(recipientUid: 2, table: 'pages', recordUid: 1, crdate: 1001));
        $this->subject->enqueue($this->buildNotification(recipientUid: 3, table: 'tt_content', recordUid: 5, crdate: 1000));
        $sentUids = array_map(static fn (array $row): int => (int) $row['uid'], $this->subject->findPending(3, 'tt_content', 5));
        $this->subject->markSent($sentUids, 5000);

        $triples = $this->subject->findDistinctPendingTriples();

        self::assertSame([['backend_user' => 2, 'tablename' => 'pages', 'record_uid' => 1]], $triples);
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
