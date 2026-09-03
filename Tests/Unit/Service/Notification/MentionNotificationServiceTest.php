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
use Xima\XimaTypo3ContentPlanner\Domain\Model\WatchSource;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Service\Notification\{MentionNotificationService, NotificationDispatcher, NotificationPayloadFactory};
use Xima\XimaTypo3ContentPlanner\Service\Notification\RecipientAccessChecker;
use Xima\XimaTypo3ContentPlanner\Service\WatcherService;

/**
 * MentionNotificationServiceTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class MentionNotificationServiceTest extends TestCase
{
    private const CONTENT_WITH_ONE_MENTION = '<p>Hey <a class="ctp-mention" data-mention-uid="42">@Jane</a>!</p>';

    #[Test]
    public function doesNothingWhenTheTableIsNotWatchable(): void
    {
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(false);
        $watcherService->expects(self::never())->method('watch');

        $notificationDispatcher = $this->createMock(NotificationDispatcher::class);
        $notificationDispatcher->expects(self::never())->method('dispatchMention');

        $subject = $this->createSubject($watcherService, $notificationDispatcher);
        $subject->notifyMentions('sys_file_metadata', 1, self::CONTENT_WITH_ONE_MENTION, 1, 5);
    }

    #[Test]
    public function doesNothingWhenTheContentHasNoMentionMarkers(): void
    {
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(true);
        $watcherService->expects(self::never())->method('watch');

        $notificationDispatcher = $this->createMock(NotificationDispatcher::class);
        $notificationDispatcher->expects(self::never())->method('dispatchMention');

        $subject = $this->createSubject($watcherService, $notificationDispatcher);
        $subject->notifyMentions('pages', 1, '<p>No mentions here.</p>', 1, 5);
    }

    #[Test]
    public function autoWatchesAndDispatchesForAMentionedPermittedActiveUser(): void
    {
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(true);
        $watcherService->expects(self::once())->method('watch')->with('pages', 1, 42, WatchSource::Mention);

        $notificationDispatcher = $this->createMock(NotificationDispatcher::class);
        $notificationDispatcher->expects(self::once())->method('dispatchMention')
            ->with('pages', 1, 7, 42, ['title' => 'Home']);

        $backendUserRepository = $this->createMock(BackendUserRepository::class);
        $backendUserRepository->method('filterActiveUids')->with([42])->willReturn([42]);
        $backendUserRepository->method('findAllWithPermission')->willReturn([
            ['uid' => 42, 'username' => 'jane'],
        ]);

        $subject = $this->createSubject($watcherService, $notificationDispatcher, $backendUserRepository);
        $subject->notifyMentions('pages', 1, self::CONTENT_WITH_ONE_MENTION, 7, 5);
    }

    #[Test]
    public function dispatchesEvenWhenTheWatcherServiceReportsTheMentionedUserIsNotWatching(): void
    {
        // The whole point of the feature: a mention must reach a non-watcher. This service must
        // not gate on isWatching()/getActiveWatchers() at all before calling dispatchMention().
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(true);
        $watcherService->expects(self::never())->method('isWatching');
        $watcherService->expects(self::never())->method('getActiveWatchers');
        $watcherService->expects(self::never())->method('getActiveWatchersWithSource');

        $notificationDispatcher = $this->createMock(NotificationDispatcher::class);
        $notificationDispatcher->expects(self::once())->method('dispatchMention');

        $backendUserRepository = $this->createMock(BackendUserRepository::class);
        $backendUserRepository->method('filterActiveUids')->willReturn([42]);
        $backendUserRepository->method('findAllWithPermission')->willReturn([
            ['uid' => 42, 'username' => 'jane'],
        ]);

        $subject = $this->createSubject($watcherService, $notificationDispatcher, $backendUserRepository);
        $subject->notifyMentions('pages', 1, self::CONTENT_WITH_ONE_MENTION, 7, 5);
    }

    #[Test]
    public function doesNotNotifyOrWatchWhenTheMentionedUserIsTheActorThemself(): void
    {
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(true);
        $watcherService->expects(self::never())->method('watch');

        $notificationDispatcher = $this->createMock(NotificationDispatcher::class);
        $notificationDispatcher->expects(self::never())->method('dispatchMention');

        $backendUserRepository = $this->createMock(BackendUserRepository::class);
        $backendUserRepository->method('filterActiveUids')->willReturn([42]);
        $backendUserRepository->method('findAllWithPermission')->willReturn([
            ['uid' => 42, 'username' => 'jane'],
        ]);

        $subject = $this->createSubject($watcherService, $notificationDispatcher, $backendUserRepository);
        // The actor mentions themself (self-mention): must be a no-op.
        $subject->notifyMentions('pages', 1, self::CONTENT_WITH_ONE_MENTION, 42, 5);
    }

    #[Test]
    public function excludesAMentionedUserWhoIsNotContentPlannerPermitted(): void
    {
        // Defense in depth: a hand-crafted mention marker referencing a uid outside the
        // permission-filtered suggestion list must never trigger a notification or auto-watch.
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(true);
        $watcherService->expects(self::never())->method('watch');

        $notificationDispatcher = $this->createMock(NotificationDispatcher::class);
        $notificationDispatcher->expects(self::never())->method('dispatchMention');

        $backendUserRepository = $this->createMock(BackendUserRepository::class);
        $backendUserRepository->method('filterActiveUids')->with([42])->willReturn([42]);
        // uid 42 is active, but not present in the CP-permitted user pool.
        $backendUserRepository->method('findAllWithPermission')->willReturn([
            ['uid' => 99, 'username' => 'someone-else'],
        ]);

        $subject = $this->createSubject($watcherService, $notificationDispatcher, $backendUserRepository);
        $subject->notifyMentions('pages', 1, self::CONTENT_WITH_ONE_MENTION, 7, 5);
    }

    #[Test]
    public function excludesAMentionedUserWhoIsDeletedOrDisabled(): void
    {
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(true);
        $watcherService->expects(self::never())->method('watch');

        $notificationDispatcher = $this->createMock(NotificationDispatcher::class);
        $notificationDispatcher->expects(self::never())->method('dispatchMention');

        $backendUserRepository = $this->createMock(BackendUserRepository::class);
        // uid 42 is not among the active uids (deleted/disabled).
        $backendUserRepository->method('filterActiveUids')->with([42])->willReturn([]);
        $backendUserRepository->expects(self::never())->method('findAllWithPermission');

        $subject = $this->createSubject($watcherService, $notificationDispatcher, $backendUserRepository);
        $subject->notifyMentions('pages', 1, self::CONTENT_WITH_ONE_MENTION, 7, 5);
    }

    #[Test]
    public function doesNotNotifyAMentionedUserWhoCannotReadTheRecord(): void
    {
        // Being allowed to use the content planner is not the same as being allowed to open
        // this record. A mention must not become a way to hand someone a page title they are
        // not permitted to see - by mail, at that.
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(true);
        $watcherService->expects(self::never())->method('watch');

        $dispatcher = $this->createMock(NotificationDispatcher::class);
        $dispatcher->expects(self::never())->method('dispatchMention');

        $backendUserRepository = $this->createMock(BackendUserRepository::class);
        $backendUserRepository->method('filterActiveUids')->willReturnArgument(0);
        $backendUserRepository->method('findAllWithPermission')->willReturn([['uid' => 7]]);

        $payloadFactory = $this->createMock(NotificationPayloadFactory::class);
        $payloadFactory->method('forMention')->willReturn([]);

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->method('findByUid')->willReturn(['uid' => 5, 'pid' => 1]);

        $accessChecker = $this->createMock(RecipientAccessChecker::class);
        $accessChecker->method('canAccess')->willReturn(false);

        $subject = new MentionNotificationService(
            $watcherService,
            $dispatcher,
            $payloadFactory,
            $backendUserRepository,
            $recordRepository,
            $accessChecker,
        );

        $subject->notifyMentions('pages', 5, $this->mentionMarkup(7), 1, 42);
    }

    #[Test]
    public function honoursAnUpperBoundOnMentionsPerComment(): void
    {
        // Without a cap, one comment mentioning everyone becomes one mail to everyone.
        $mentionedUids = range(1001, 1030);
        $content = implode(' ', array_map($this->mentionMarkup(...), $mentionedUids));

        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(true);

        $dispatched = 0;
        $dispatcher = $this->createMock(NotificationDispatcher::class);
        $dispatcher->method('dispatchMention')->willReturnCallback(static function () use (&$dispatched): void {
            ++$dispatched;
        });

        $backendUserRepository = $this->createMock(BackendUserRepository::class);
        $backendUserRepository->method('filterActiveUids')->willReturnArgument(0);
        $backendUserRepository->method('findAllWithPermission')->willReturn(
            array_map(static fn (int $uid): array => ['uid' => $uid], $mentionedUids),
        );

        $payloadFactory = $this->createMock(NotificationPayloadFactory::class);
        $payloadFactory->method('forMention')->willReturn([]);

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->method('findByUid')->willReturn(['uid' => 5, 'pid' => 1]);

        $accessChecker = $this->createMock(RecipientAccessChecker::class);
        $accessChecker->method('canAccess')->willReturn(true);

        $subject = new MentionNotificationService(
            $watcherService,
            $dispatcher,
            $payloadFactory,
            $backendUserRepository,
            $recordRepository,
            $accessChecker,
        );

        $subject->notifyMentions('pages', 5, $content, 1, 42);

        self::assertLessThanOrEqual(10, $dispatched);
        self::assertGreaterThan(0, $dispatched);
    }

    private function mentionMarkup(int $uid): string
    {
        return '<a class="ctp-mention" data-mention-uid="'.$uid.'">@user</a>';
    }

    private function createSubject(
        WatcherService $watcherService,
        NotificationDispatcher $notificationDispatcher,
        ?BackendUserRepository $backendUserRepository = null,
    ): MentionNotificationService {
        $payloadFactory = $this->createMock(NotificationPayloadFactory::class);
        $payloadFactory->method('forMention')->willReturn(['title' => 'Home']);

        // The record exists and the mentioned user may read it; the access rules themselves
        // are covered by RecipientAccessChecker's own tests.
        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->method('findByUid')->willReturn(['uid' => 5, 'pid' => 1]);
        $accessChecker = $this->createMock(RecipientAccessChecker::class);
        $accessChecker->method('canAccess')->willReturn(true);

        return new MentionNotificationService(
            $watcherService,
            $notificationDispatcher,
            $payloadFactory,
            $backendUserRepository ?? $this->createMock(BackendUserRepository::class),
            $recordRepository,
            $accessChecker,
        );
    }
}
