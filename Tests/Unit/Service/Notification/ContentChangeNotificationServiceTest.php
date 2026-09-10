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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Service\Notification;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Domain\Model\NotificationEventType;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Service\Notification\{ContentChangeNotificationService, NotificationDispatcher, NotificationPayloadFactory};
use Xima\XimaTypo3ContentPlanner\Service\WatcherService;

/**
 * ContentChangeNotificationServiceTest.
 *
 * Covers issue #309's "no DataHandler overhead for records without watchers" acceptance
 * criterion at the unit level: {@see WatcherService::getActiveWatchers()} is the only collaborator
 * ever queried for a record nobody watches - the payload factory (which itself resolves the
 * record's title, another query) and the dispatcher are never reached in that case.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ContentChangeNotificationServiceTest extends TestCase
{
    #[Test]
    public function recordChangeStopsAfterTheWatcherCheckWhenNobodyWatchesTheRecord(): void
    {
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(true);
        $watcherService->expects(self::once())->method('getActiveWatchers')->with('pages', 1)->willReturn([]);

        $payloadFactory = $this->createMock(NotificationPayloadFactory::class);
        $payloadFactory->expects(self::never())->method('forContentChanged');

        $dispatcher = $this->createMock(NotificationDispatcher::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $this->buildService($watcherService, $dispatcher, payloadFactory: $payloadFactory)
            ->recordChange('pages', 1, 5);
    }

    #[Test]
    public function recordChangeDispatchesWhenTheRecordHasWatchers(): void
    {
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(true);
        $watcherService->method('getActiveWatchers')->with('pages', 1)->willReturn([2, 3]);

        $payloadFactory = $this->createMock(NotificationPayloadFactory::class);
        $payloadFactory->method('forContentChanged')->with('pages', 1, 5)->willReturn(['changeCount' => 1]);

        $dispatcher = $this->createMock(NotificationDispatcher::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(NotificationEventType::ContentChanged, 'pages', 1, 5, ['changeCount' => 1]);

        $this->buildService($watcherService, $dispatcher, payloadFactory: $payloadFactory)
            ->recordChange('pages', 1, 5);
    }

    #[Test]
    public function recordChangeOnAContentElementAlsoNotifiesItsPagesWatchers(): void
    {
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(true);
        $watcherService->method('getActiveWatchers')->willReturnMap([
            ['tt_content', 42, []],
            ['pages', 7, [9]],
        ]);

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->method('findPidByUid')->with('tt_content', 42)->willReturn(7);

        $payloadFactory = $this->createMock(NotificationPayloadFactory::class);
        $payloadFactory->method('forContentChanged')->willReturn([]);

        $dispatcher = $this->createMock(NotificationDispatcher::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(NotificationEventType::ContentChanged, 'pages', 7, 5, []);

        $this->buildService($watcherService, $dispatcher, $recordRepository, $payloadFactory)
            ->recordChange('tt_content', 42, 5);
    }

    #[Test]
    public function recordChangeSkipsPagePropagationWhenTheContentElementHasNoResolvablePage(): void
    {
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(true);
        $watcherService->method('getActiveWatchers')->willReturn([]);

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->method('findPidByUid')->willReturn(null);

        $dispatcher = $this->createMock(NotificationDispatcher::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $this->buildService($watcherService, $dispatcher, $recordRepository)
            ->recordChange('tt_content', 42, 5);
    }

    private function buildService(
        WatcherService $watcherService,
        NotificationDispatcher $dispatcher,
        ?RecordRepository $recordRepository = null,
        ?NotificationPayloadFactory $payloadFactory = null,
    ): ContentChangeNotificationService {
        return new ContentChangeNotificationService(
            $watcherService,
            $dispatcher,
            $payloadFactory ?? $this->createMock(NotificationPayloadFactory::class),
            $recordRepository ?? $this->createMock(RecordRepository::class),
        );
    }
}
