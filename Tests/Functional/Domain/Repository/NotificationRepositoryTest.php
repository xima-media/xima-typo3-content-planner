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
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\{Notification, NotificationEventType, NotificationReason};
use Xima\XimaTypo3ContentPlanner\Domain\Repository\NotificationRepository;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * NotificationRepositoryTest.
 *
 * Covers the read/mutation surface added for the backend toolbar notification center (issue
 * #301): unread counting, the unread-first dropdown ordering, and that mark-as-read is always
 * scoped to the notification's own recipient.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class NotificationRepositoryTest extends AbstractFunctionalTestCase
{
    private NotificationRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->get(NotificationRepository::class);
    }

    #[Test]
    public function countUnreadByRecipientCountsOnlyUnreadRowsForThatUser(): void
    {
        $this->createNotification(recipientUid: 1, crdate: 1000);
        $this->createNotification(recipientUid: 1, crdate: 1001);
        $this->createNotification(recipientUid: 2, crdate: 1002);
        $this->markRowReadByCrdate(1001);

        self::assertSame(1, $this->subject->countUnreadByRecipient(1));
        self::assertSame(1, $this->subject->countUnreadByRecipient(2));
        self::assertSame(0, $this->subject->countUnreadByRecipient(99));
    }

    #[Test]
    public function findLatestByRecipientOrdersUnreadFirstThenMostRecent(): void
    {
        $this->createNotification(recipientUid: 1, crdate: 1000); // read, oldest
        $this->createNotification(recipientUid: 1, crdate: 2000); // unread, older of the two unread
        $this->createNotification(recipientUid: 1, crdate: 3000); // unread, newest
        $this->markRowReadByCrdate(1000);

        $rows = $this->subject->findLatestByRecipient(1, 10);

        self::assertSame([3000, 2000, 1000], array_map(static fn (array $row): int => (int) $row['crdate'], $rows));
    }

    #[Test]
    public function findLatestByRecipientSortsReadRowsByCrdateNotReadAt(): void
    {
        $this->createNotification(recipientUid: 1, crdate: 1000); // read first (earlier read_at), older crdate
        $this->createNotification(recipientUid: 1, crdate: 2000); // read last (later read_at), newer crdate
        $this->markRowReadAtByCrdate(1000, 1000);
        $this->markRowReadAtByCrdate(2000, 9000);

        $rows = $this->subject->findLatestByRecipient(1, 10);

        // Both rows are read; sorting by read_at instead of crdate would yield [1000, 2000].
        self::assertSame([2000, 1000], array_map(static fn (array $row): int => (int) $row['crdate'], $rows));
    }

    #[Test]
    public function findLatestByRecipientRespectsTheLimit(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->createNotification(recipientUid: 1, crdate: 1000 + $i);
        }

        self::assertCount(2, $this->subject->findLatestByRecipient(1, 2));
    }

    #[Test]
    public function findLatestByRecipientOnlyReturnsRowsForThatRecipient(): void
    {
        $this->createNotification(recipientUid: 1, crdate: 1000);
        $this->createNotification(recipientUid: 2, crdate: 1001);

        $rows = $this->subject->findLatestByRecipient(1, 10);

        self::assertCount(1, $rows);
        self::assertSame(1, (int) $rows[0]['backend_user']);
    }

    #[Test]
    public function markAsReadSetsReadAtAndReturnsTrue(): void
    {
        $this->createNotification(recipientUid: 1, crdate: 1000);
        $uid = $this->fetchUidByCrdate(1000);

        self::assertTrue($this->subject->markAsRead($uid, 1));
        self::assertSame(0, $this->subject->countUnreadByRecipient(1));
    }

    #[Test]
    public function markAsReadReturnsFalseWhenNotificationBelongsToAnotherUser(): void
    {
        $this->createNotification(recipientUid: 1, crdate: 1000);
        $uid = $this->fetchUidByCrdate(1000);

        self::assertFalse($this->subject->markAsRead($uid, 2));
        self::assertSame(1, $this->subject->countUnreadByRecipient(1));
    }

    #[Test]
    public function markAsReadIsIdempotent(): void
    {
        $this->createNotification(recipientUid: 1, crdate: 1000);
        $uid = $this->fetchUidByCrdate(1000);

        self::assertTrue($this->subject->markAsRead($uid, 1));
        self::assertFalse($this->subject->markAsRead($uid, 1));
    }

    #[Test]
    public function markAllAsReadOnlyAffectsTheGivenRecipientsUnreadRows(): void
    {
        $this->createNotification(recipientUid: 1, crdate: 1000);
        $this->createNotification(recipientUid: 1, crdate: 1001);
        $this->createNotification(recipientUid: 2, crdate: 1002);

        $affected = $this->subject->markAllAsRead(1);

        self::assertSame(2, $affected);
        self::assertSame(0, $this->subject->countUnreadByRecipient(1));
        self::assertSame(1, $this->subject->countUnreadByRecipient(2));
    }

    #[Test]
    public function deleteOlderThanDeletesOnlyReadRowsOlderThanTheThreshold(): void
    {
        $this->createNotification(recipientUid: 1, crdate: 1000); // read, old -> deleted
        $this->createNotification(recipientUid: 1, crdate: 5000); // read, new -> kept
        $this->createNotification(recipientUid: 1, crdate: 900); // unread, old -> kept (wrong read state)
        $this->markRowReadByCrdate(1000);
        $this->markRowReadByCrdate(5000);

        $deleted = $this->subject->deleteOlderThan(true, 2000, false);

        self::assertSame(1, $deleted);
        self::assertSame([5000, 900], array_map(static fn (array $row): int => (int) $row['crdate'], $this->fetchAllOrderedByCrdateDesc()));
    }

    #[Test]
    public function deleteOlderThanDeletesOnlyUnreadRowsOlderThanTheThreshold(): void
    {
        $this->createNotification(recipientUid: 1, crdate: 1000); // unread, old -> deleted
        $this->createNotification(recipientUid: 1, crdate: 5000); // unread, new -> kept
        $this->createNotification(recipientUid: 1, crdate: 900); // read, old -> kept (wrong read state)
        $this->markRowReadByCrdate(900);

        $deleted = $this->subject->deleteOlderThan(false, 2000, false);

        self::assertSame(1, $deleted);
        self::assertSame([5000, 900], array_map(static fn (array $row): int => (int) $row['crdate'], $this->fetchAllOrderedByCrdateDesc()));
    }

    #[Test]
    public function deleteOlderThanWithDryRunOnlyCountsAndDeletesNothing(): void
    {
        $this->createNotification(recipientUid: 1, crdate: 1000);
        $this->markRowReadByCrdate(1000);

        $counted = $this->subject->deleteOlderThan(true, 2000, true);

        self::assertSame(1, $counted);
        self::assertCount(1, $this->fetchAllOrderedByCrdateDesc());
    }

    #[Test]
    public function deleteOlderThanDeletesAcrossMultipleChunks(): void
    {
        // Exceeds NotificationRepository's DELETE_CHUNK_SIZE (500), forcing a second batch.
        $count = 501;
        $connection = $this->getConnectionPool()->getConnectionForTable(Configuration::TABLE_NOTIFICATION);
        for ($i = 0; $i < $count; ++$i) {
            $connection->insert(Configuration::TABLE_NOTIFICATION, [
                'pid' => 0,
                'backend_user' => 1,
                'event_type' => 'status_changed',
                'tablename' => 'pages',
                'record_uid' => 1,
                'reason' => 'watching_manually',
                'payload' => '{}',
                'crdate' => 1000,
                'read_at' => time(),
            ]);
        }

        $deleted = $this->subject->deleteOlderThan(true, 2000, false);

        self::assertSame($count, $deleted);
        self::assertSame(0, (int) $connection->count('*', Configuration::TABLE_NOTIFICATION, []));
    }

    #[Test]
    public function findDistinctTableRecordPairsReturnsEachPairOnce(): void
    {
        $this->createStatusChangeFor('pages', 1);
        $this->createStatusChangeFor('pages', 1);
        $this->createStatusChangeFor('pages', 2);
        $this->createStatusChangeFor('tt_content', 1);

        $pairs = $this->subject->findDistinctTableRecordPairs();

        self::assertCount(3, $pairs);
        self::assertContains(['tablename' => 'pages', 'record_uid' => 1], $pairs);
        self::assertContains(['tablename' => 'pages', 'record_uid' => 2], $pairs);
        self::assertContains(['tablename' => 'tt_content', 'record_uid' => 1], $pairs);
    }

    #[Test]
    public function findDistinctBackendUsersReturnsEachRecipientOnce(): void
    {
        $this->createNotification(recipientUid: 1, crdate: 1000);
        $this->createNotification(recipientUid: 1, crdate: 1001);
        $this->createNotification(recipientUid: 2, crdate: 1002);

        $result = $this->subject->findDistinctBackendUsers();
        sort($result);
        self::assertSame([1, 2], $result);
    }

    #[Test]
    public function deleteForTableAndRecordUidsOnlyDeletesMatchingRows(): void
    {
        $this->createStatusChangeFor('pages', 1);
        $this->createStatusChangeFor('pages', 2);
        $this->createStatusChangeFor('tt_content', 1);

        $deleted = $this->subject->deleteForTableAndRecordUids('pages', [1], false);

        self::assertSame(1, $deleted);
        $remaining = $this->subject->findDistinctTableRecordPairs();
        self::assertNotContains(['tablename' => 'pages', 'record_uid' => 1], $remaining);
        self::assertContains(['tablename' => 'pages', 'record_uid' => 2], $remaining);
        self::assertContains(['tablename' => 'tt_content', 'record_uid' => 1], $remaining);
    }

    #[Test]
    public function deleteForTableAndRecordUidsReturnsZeroForAnEmptyList(): void
    {
        self::assertSame(0, $this->subject->deleteForTableAndRecordUids('pages', [], false));
    }

    #[Test]
    public function deleteForBackendUsersOnlyDeletesMatchingRows(): void
    {
        $this->createNotification(recipientUid: 1, crdate: 1000);
        $this->createNotification(recipientUid: 2, crdate: 1001);

        $deleted = $this->subject->deleteForBackendUsers([1], false);

        self::assertSame(1, $deleted);
        self::assertSame([2], $this->subject->findDistinctBackendUsers());
    }

    #[Test]
    public function deleteForBackendUsersReturnsZeroForAnEmptyList(): void
    {
        self::assertSame(0, $this->subject->deleteForBackendUsers([], false));
    }

    private function createNotification(int $recipientUid, int $crdate): void
    {
        $this->subject->create(new Notification(
            $recipientUid,
            NotificationEventType::StatusChanged,
            'pages',
            1,
            null,
            NotificationReason::WatchingManually,
            ['version' => 1, 'title' => 'Home'],
            $crdate,
        ));
    }

    private function createStatusChangeFor(string $table, int $recordUid): void
    {
        $this->subject->create(new Notification(
            1,
            NotificationEventType::StatusChanged,
            $table,
            $recordUid,
            null,
            NotificationReason::WatchingManually,
            ['version' => 1, 'title' => 'Home'],
            time(),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAllOrderedByCrdateDesc(): array
    {
        return $this->getConnectionPool()
            ->getConnectionForTable(Configuration::TABLE_NOTIFICATION)
            ->select(['*'], Configuration::TABLE_NOTIFICATION, [], [], ['crdate' => 'DESC'])
            ->fetchAllAssociative();
    }

    private function markRowReadByCrdate(int $crdate): void
    {
        $this->markRowReadAtByCrdate($crdate, time());
    }

    private function markRowReadAtByCrdate(int $crdate, int $readAt): void
    {
        $this->getConnectionPool()
            ->getConnectionForTable(Configuration::TABLE_NOTIFICATION)
            ->update(Configuration::TABLE_NOTIFICATION, ['read_at' => $readAt], ['crdate' => $crdate]);
    }

    private function fetchUidByCrdate(int $crdate): int
    {
        $row = $this->getConnectionPool()
            ->getConnectionForTable(Configuration::TABLE_NOTIFICATION)
            ->select(['uid'], Configuration::TABLE_NOTIFICATION, ['crdate' => $crdate])
            ->fetchAssociative();

        return (int) $row['uid'];
    }
}
