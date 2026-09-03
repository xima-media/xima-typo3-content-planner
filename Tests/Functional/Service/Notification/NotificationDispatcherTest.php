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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Service\Notification;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\{NotificationEventType, WatchSource};
use Xima\XimaTypo3ContentPlanner\Service\Notification\NotificationDispatcher;
use Xima\XimaTypo3ContentPlanner\Service\WatcherService;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * NotificationDispatcherTest.
 *
 * Wires the real {@see WatcherService} and the real, DI-tagged
 * {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\Channel\DatabaseChannel} against a real
 * database, per issue #300's acceptance criteria: "Dispatcher resolves watchers and excludes the
 * actor" and "status change with 3 watchers incl. actor -> 2 records".
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class NotificationDispatcherTest extends AbstractFunctionalTestCase
{
    private NotificationDispatcher $subject;
    private WatcherService $watcherService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__.'/../Fixtures/pages.csv');
        $this->loginBackendUser();
        $this->enableExtensionFeature(Configuration::FEATURE_NOTIFICATION_CHANNEL_DATABASE, '1');

        $this->subject = $this->get(NotificationDispatcher::class);
        $this->watcherService = $this->get(WatcherService::class);
    }

    #[Test]
    public function dispatchPersistsOneNotificationPerWatcherExcludingTheActor(): void
    {
        $this->watcherService->watch('pages', 1, 1, WatchSource::StatusChange); // the actor
        $this->watcherService->watch('pages', 1, 2, WatchSource::Assignment);
        $this->watcherService->watch('pages', 1, 3, WatchSource::Manual);

        $this->subject->dispatch(NotificationEventType::StatusChanged, 'pages', 1, 1, ['version' => 1, 'title' => 'Home']);

        $rows = $this->fetchAllNotifications();
        self::assertCount(2, $rows);
        self::assertSame([2, 3], array_map(static fn (array $row): int => (int) $row['backend_user'], $rows));
        self::assertSame('watching_since_assignment', $rows[0]['reason']);
        self::assertSame('watching_manually', $rows[1]['reason']);
        self::assertSame('status_changed', $rows[0]['event_type']);
        self::assertSame(1, (int) $rows[0]['actor']);
        self::assertSame('{"version":1,"title":"Home"}', $rows[0]['payload']);
    }

    #[Test]
    public function dispatchDoesNothingWhenNoOneIsWatching(): void
    {
        $this->subject->dispatch(NotificationEventType::StatusChanged, 'pages', 1, 1, []);

        self::assertCount(0, $this->fetchAllNotifications());
    }

    #[Test]
    public function dispatchDoesNothingForAnUnwatchableTable(): void
    {
        $this->subject->dispatch(NotificationEventType::StatusChanged, 'sys_file_metadata', 1, null, []);

        self::assertCount(0, $this->fetchAllNotifications());
    }

    #[Test]
    public function dispatchSkipsTheDatabaseChannelWhenItIsDisabled(): void
    {
        $this->enableExtensionFeature(Configuration::FEATURE_NOTIFICATION_CHANNEL_DATABASE, '0');
        $this->watcherService->watch('pages', 1, 2, WatchSource::Manual);

        $this->subject->dispatch(NotificationEventType::StatusChanged, 'pages', 1, 1, []);

        self::assertCount(0, $this->fetchAllNotifications());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAllNotifications(): array
    {
        return $this->getConnectionPool()
            ->getConnectionForTable(Configuration::TABLE_NOTIFICATION)
            ->select(['*'], Configuration::TABLE_NOTIFICATION, [], [], ['backend_user' => 'ASC'])
            ->fetchAllAssociative();
    }
}
