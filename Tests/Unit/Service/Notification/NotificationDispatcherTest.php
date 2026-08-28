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
use Xima\XimaTypo3ContentPlanner\Domain\Model\{Notification, NotificationEventType, NotificationReason, WatchSource};
use Xima\XimaTypo3ContentPlanner\Service\Notification\{NotificationChannelInterface, NotificationDispatcher, NotificationSuppressionState};
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Service\WatcherService;

/**
 * NotificationDispatcherTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class NotificationDispatcherTest extends TestCase
{
    #[Test]
    public function dispatchesOneNotificationPerActiveWatcherExcludingTheActor(): void
    {
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(true);
        $watcherService->method('getActiveWatchersWithSource')->willReturn([
            1 => WatchSource::StatusChange, // the actor - must be excluded
            2 => WatchSource::Assignment,
            3 => WatchSource::Manual,
        ]);

        $delivered = [];
        $channel = $this->createMock(NotificationChannelInterface::class);
        $channel->method('supports')->willReturn(true);
        $channel->method('deliver')->willReturnCallback(static function (Notification $notification) use (&$delivered): void {
            $delivered[] = $notification;
        });

        $subject = new NotificationDispatcher($watcherService, new NotificationSuppressionState(), $this->createBackendUserRepository(), [$channel]);
        $subject->dispatch(NotificationEventType::StatusChanged, 'pages', 5, 1, ['title' => 'Home']);

        self::assertCount(2, $delivered);
        self::assertSame([2, 3], array_map(static fn (Notification $notification): int => $notification->getRecipientUid(), $delivered));
        self::assertSame(NotificationReason::WatchingSinceAssignment, $delivered[0]->getReason());
        self::assertSame(NotificationReason::WatchingManually, $delivered[1]->getReason());
        self::assertSame(1, $delivered[0]->getActorUid());
        self::assertSame(['title' => 'Home'], $delivered[0]->getPayload());
    }

    #[Test]
    public function doesNotDeliverToChannelsThatDoNotSupportTheNotification(): void
    {
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(true);
        $watcherService->method('getActiveWatchersWithSource')->willReturn([2 => WatchSource::Manual]);

        $channel = $this->createMock(NotificationChannelInterface::class);
        $channel->method('supports')->willReturn(false);
        $channel->expects(self::never())->method('deliver');

        $subject = new NotificationDispatcher($watcherService, new NotificationSuppressionState(), $this->createBackendUserRepository(), [$channel]);
        $subject->dispatch(NotificationEventType::CommentAdded, 'pages', 5, null, []);
    }

    #[Test]
    public function doesNothingWhenSuppressionStateIsPaused(): void
    {
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->expects(self::never())->method('getActiveWatchersWithSource');

        $channel = $this->createMock(NotificationChannelInterface::class);
        $channel->expects(self::never())->method('deliver');

        $suppressionState = new NotificationSuppressionState();
        $suppressionState->pause();

        $subject = new NotificationDispatcher($watcherService, $suppressionState, $this->createBackendUserRepository(), [$channel]);
        $subject->dispatch(NotificationEventType::StatusChanged, 'pages', 5, 1, []);
    }

    #[Test]
    public function doesNothingWhenTheTableIsNotWatchable(): void
    {
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(false);
        $watcherService->expects(self::never())->method('getActiveWatchersWithSource');

        $channel = $this->createMock(NotificationChannelInterface::class);
        $channel->expects(self::never())->method('deliver');

        $subject = new NotificationDispatcher($watcherService, new NotificationSuppressionState(), $this->createBackendUserRepository(), [$channel]);
        $subject->dispatch(NotificationEventType::StatusChanged, 'sys_file_metadata', 5, 1, []);
    }

    #[Test]
    public function excludingTheActorLeavesEveryOtherWatcherUntouchedWhenActorIsNull(): void
    {
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(true);
        $watcherService->method('getActiveWatchersWithSource')->willReturn([2 => WatchSource::Manual]);

        $channel = $this->createMock(NotificationChannelInterface::class);
        $channel->method('supports')->willReturn(true);
        $channel->expects(self::once())->method('deliver');

        $subject = new NotificationDispatcher($watcherService, new NotificationSuppressionState(), $this->createBackendUserRepository(), [$channel]);
        $subject->dispatch(NotificationEventType::StatusChanged, 'pages', 5, null, []);
    }

    #[Test]
    public function skipsWatchersWhoseAccountIsDeletedOrDisabled(): void
    {
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(true);
        $watcherService->method('getActiveWatchersWithSource')->willReturn([
            2 => WatchSource::Manual,
            3 => WatchSource::Manual,
        ]);

        // A watcher row outlives the account it belongs to, so user 3 is gone even though the
        // relation still exists.
        $repository = $this->createMock(BackendUserRepository::class);
        $repository->method('filterActiveUids')->willReturn([2]);

        $delivered = [];
        $channel = $this->createMock(NotificationChannelInterface::class);
        $channel->method('supports')->willReturn(true);
        $channel->method('deliver')->willReturnCallback(static function (Notification $notification) use (&$delivered): void {
            $delivered[] = $notification->getRecipientUid();
        });

        $subject = new NotificationDispatcher($watcherService, new NotificationSuppressionState(), $repository, [$channel]);
        $subject->dispatch(NotificationEventType::StatusChanged, 'pages', 5, 1, []);

        self::assertSame([2], $delivered);
    }

    // ==================== dispatchMention() ====================

    #[Test]
    public function dispatchMentionDeliversToTheRecipientEvenWhenTheyAreNotAnActiveWatcher(): void
    {
        // The whole point of dispatchMention(): it must never even ask whether the recipient is
        // a watcher - a mention has to reach someone regardless of their current watch state.
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(true);
        $watcherService->expects(self::never())->method('getActiveWatchersWithSource');
        $watcherService->expects(self::never())->method('isWatching');
        $watcherService->expects(self::never())->method('getActiveWatchers');

        $delivered = [];
        $channel = $this->createMock(NotificationChannelInterface::class);
        $channel->method('supports')->willReturn(true);
        $channel->method('deliver')->willReturnCallback(static function (Notification $notification) use (&$delivered): void {
            $delivered[] = $notification;
        });

        $subject = new NotificationDispatcher($watcherService, new NotificationSuppressionState(), $this->createBackendUserRepository(), [$channel]);
        $subject->dispatchMention('pages', 5, 1, 2, ['title' => 'Home']);

        self::assertCount(1, $delivered);
        self::assertSame(2, $delivered[0]->getRecipientUid());
        self::assertSame(1, $delivered[0]->getActorUid());
        self::assertSame(NotificationEventType::Mentioned, $delivered[0]->getEventType());
        self::assertSame(NotificationReason::Mentioned, $delivered[0]->getReason());
        self::assertSame(['title' => 'Home'], $delivered[0]->getPayload());
    }

    #[Test]
    public function dispatchMentionNeverNotifiesTheActorAboutTheirOwnMention(): void
    {
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(true);

        $channel = $this->createMock(NotificationChannelInterface::class);
        $channel->expects(self::never())->method('deliver');

        $subject = new NotificationDispatcher($watcherService, new NotificationSuppressionState(), $this->createBackendUserRepository(), [$channel]);
        $subject->dispatchMention('pages', 5, 1, 1, []);
    }

    #[Test]
    public function dispatchMentionDoesNothingWhenSuppressionStateIsPaused(): void
    {
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->expects(self::never())->method('isWatchable');

        $channel = $this->createMock(NotificationChannelInterface::class);
        $channel->expects(self::never())->method('deliver');

        $suppressionState = new NotificationSuppressionState();
        $suppressionState->pause();

        $subject = new NotificationDispatcher($watcherService, $suppressionState, $this->createBackendUserRepository(), [$channel]);
        $subject->dispatchMention('pages', 5, 1, 2, []);
    }

    #[Test]
    public function dispatchMentionDoesNothingWhenTheTableIsNotWatchable(): void
    {
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(false);

        $channel = $this->createMock(NotificationChannelInterface::class);
        $channel->expects(self::never())->method('deliver');

        $subject = new NotificationDispatcher($watcherService, new NotificationSuppressionState(), $this->createBackendUserRepository(), [$channel]);
        $subject->dispatchMention('sys_file_metadata', 5, 1, 2, []);
    }

    #[Test]
    public function dispatchMentionSkipsChannelsThatDoNotSupportTheNotification(): void
    {
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(true);

        $channel = $this->createMock(NotificationChannelInterface::class);
        $channel->method('supports')->willReturn(false);
        $channel->expects(self::never())->method('deliver');

        $subject = new NotificationDispatcher($watcherService, new NotificationSuppressionState(), $this->createBackendUserRepository(), [$channel]);
        $subject->dispatchMention('pages', 5, 1, 2, []);
    }

    /**
     * Every watcher in these tests is an existing, enabled account; the point under test is
     * the dispatch logic, not the recipient filter.
     */
    private function createBackendUserRepository(): BackendUserRepository
    {
        $repository = $this->createMock(BackendUserRepository::class);
        $repository->method('filterActiveUids')->willReturnArgument(0);

        return $repository;
    }
}
