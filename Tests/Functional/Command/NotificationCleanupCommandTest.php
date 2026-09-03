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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Xima\XimaTypo3ContentPlanner\Command\NotificationCleanupCommand;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\{Notification, NotificationEventType, NotificationReason, WatchMode, WatchSource};
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, NotificationRepository, RecordRepository, WatcherRepository};
use Xima\XimaTypo3ContentPlanner\Service\Notification\Retention\NotificationRetentionService;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * NotificationCleanupCommandTest.
 *
 * Functional coverage for issue #304's acceptance criteria: the read/unread age-based retention
 * rules, orphan cleanup for deleted records and for deleted/disabled backend users, `--dry-run`
 * leaving the database untouched, and that the configured thresholds (not hardcoded ones) drive
 * the age-based rules.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class NotificationCleanupCommandTest extends AbstractFunctionalTestCase
{
    private const SECONDS_PER_DAY = 86400;

    private const READ_RETENTION_DAYS = 5;

    private const UNREAD_RETENTION_DAYS = 10;

    private CommandTester $tester;

    private NotificationRepository $notificationRepository;

    private WatcherRepository $watcherRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/be_users_retention.csv');
        $this->enableExtensionFeature(Configuration::CONF_NOTIFICATION_RETENTION_READ_DAYS, (string) self::READ_RETENTION_DAYS);
        $this->enableExtensionFeature(Configuration::CONF_NOTIFICATION_RETENTION_UNREAD_DAYS, (string) self::UNREAD_RETENTION_DAYS);

        $this->notificationRepository = $this->get(NotificationRepository::class);
        $this->watcherRepository = $this->get(WatcherRepository::class);

        $retentionService = new NotificationRetentionService(
            $this->notificationRepository,
            $this->watcherRepository,
            $this->get(RecordRepository::class),
            $this->get(BackendUserRepository::class),
        );

        $command = new NotificationCleanupCommand($retentionService);
        $this->tester = new CommandTester($command);
    }

    #[Test]
    public function deletesReadNotificationsOlderThanTheConfiguredReadRetention(): void
    {
        $this->createNotification(crdate: $this->daysAgo(self::READ_RETENTION_DAYS + 1), read: true);
        $this->createNotification(crdate: $this->daysAgo(self::READ_RETENTION_DAYS - 1), read: true);

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertCount(1, $this->fetchAllNotifications());
        self::assertStringContainsString('Deleted 1 read notification(s)', $this->tester->getDisplay());
    }

    #[Test]
    public function anUnreadNotificationIsNotSubjectToTheReadRetentionThreshold(): void
    {
        // Older than the read threshold (5 days) but younger than the unread one (10 days):
        // a read row this age would be deleted, this unread one must survive.
        $this->createNotification(crdate: $this->daysAgo(self::READ_RETENTION_DAYS + 1), read: false);

        $this->tester->execute([]);

        self::assertCount(1, $this->fetchAllNotifications());
    }

    #[Test]
    public function deletesUnreadNotificationsOlderThanTheConfiguredUnreadRetention(): void
    {
        $this->createNotification(crdate: $this->daysAgo(self::UNREAD_RETENTION_DAYS + 1), read: false);
        $this->createNotification(crdate: $this->daysAgo(self::UNREAD_RETENTION_DAYS - 1), read: false);

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertCount(1, $this->fetchAllNotifications());
        self::assertStringContainsString('Deleted 1 unread notification(s)', $this->tester->getDisplay());
    }

    #[Test]
    public function deletesOrphanedNotificationsAndWatchersForARecordThatNoLongerExists(): void
    {
        // Page 1 exists in the fixture, page 999 does not.
        $this->createNotificationForRecord('pages', 1);
        $this->createNotificationForRecord('pages', 999);
        $this->watcherRepository->upsert('pages', 1, 1, WatchMode::Auto, WatchSource::Assignment);
        $this->watcherRepository->upsert('pages', 999, 1, WatchMode::Auto, WatchSource::Assignment);

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Deleted 1 orphaned notification(s)', $this->tester->getDisplay());
        self::assertStringContainsString('Deleted 1 orphaned watcher(s)', $this->tester->getDisplay());
        self::assertSame([1], array_map(
            static fn (array $row): int => (int) $row['record_uid'],
            $this->notificationRepository->findDistinctTableRecordPairs(),
        ));
        self::assertSame([1], $this->watcherRepository->findActiveWatcherUserIds('pages', 1));
        self::assertSame([], $this->watcherRepository->findActiveWatcherUserIds('pages', 999));
    }

    #[Test]
    public function deletesOrphanedNotificationsAndWatchersForADeletedOrDisabledBackendUser(): void
    {
        $this->createNotification(crdate: time(), read: false, recipientUid: 2); // active
        $this->createNotification(crdate: time(), read: false, recipientUid: 3); // disabled
        $this->createNotification(crdate: time(), read: false, recipientUid: 4); // deleted
        $this->watcherRepository->upsert('pages', 1, 2, WatchMode::Auto, WatchSource::Assignment);
        $this->watcherRepository->upsert('pages', 1, 3, WatchMode::Auto, WatchSource::Assignment);

        $exitCode = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Deleted 2 orphaned notification(s)', $this->tester->getDisplay());
        self::assertStringContainsString('Deleted 1 orphaned watcher(s)', $this->tester->getDisplay());
        self::assertSame([2], $this->notificationRepository->findDistinctBackendUsers());
        self::assertSame([2], $this->watcherRepository->findDistinctBackendUsers());
    }

    #[Test]
    public function dryRunReportsCountsAndDeletesNothing(): void
    {
        $this->createNotification(crdate: $this->daysAgo(self::READ_RETENTION_DAYS + 1), read: true);
        $this->createNotificationForRecord('pages', 999);

        $exitCode = $this->tester->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Would delete 1 read notification(s)', $this->tester->getDisplay());
        self::assertStringContainsString('Would delete 1 orphaned notification(s)', $this->tester->getDisplay());
        self::assertCount(2, $this->fetchAllNotifications());
    }

    private function daysAgo(int $days): int
    {
        return time() - $days * self::SECONDS_PER_DAY;
    }

    private function createNotification(int $crdate, bool $read, int $recipientUid = 1): void
    {
        $this->notificationRepository->create(new Notification(
            $recipientUid,
            NotificationEventType::StatusChanged,
            'pages',
            1,
            null,
            NotificationReason::WatchingManually,
            ['version' => 1, 'title' => 'Home'],
            $crdate,
        ));

        if ($read) {
            $this->getConnectionPool()
                ->getConnectionForTable(Configuration::TABLE_NOTIFICATION)
                ->update(Configuration::TABLE_NOTIFICATION, ['read_at' => time()], ['crdate' => $crdate, 'backend_user' => $recipientUid]);
        }
    }

    private function createNotificationForRecord(string $table, int $recordUid): void
    {
        $this->notificationRepository->create(new Notification(
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
    private function fetchAllNotifications(): array
    {
        return $this->getConnectionPool()
            ->getConnectionForTable(Configuration::TABLE_NOTIFICATION)
            ->select(['*'], Configuration::TABLE_NOTIFICATION)
            ->fetchAllAssociative();
    }
}
