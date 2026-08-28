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

    private function markRowReadByCrdate(int $crdate): void
    {
        $this->getConnectionPool()
            ->getConnectionForTable(Configuration::TABLE_NOTIFICATION)
            ->update(Configuration::TABLE_NOTIFICATION, ['read_at' => time()], ['crdate' => $crdate]);
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
