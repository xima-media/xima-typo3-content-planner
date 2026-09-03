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

namespace Xima\XimaTypo3ContentPlanner\Service\Notification;

use Doctrine\DBAL\Exception;
use Xima\XimaTypo3ContentPlanner\Domain\Model\NotificationEventType;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Service\WatcherService;

/**
 * ContentChangeNotificationService.
 *
 * Drives issue #309's content-change notifications from {@see \Xima\XimaTypo3ContentPlanner\Hooks\DataHandlerHook}:
 * given one changed record, notifies its own watchers and - for a `tt_content` change - also the
 * parent page's watchers, per the issue's "edits to content elements on the page count as a
 * change to the watched page" rule.
 *
 * The feature flag gate ({@see \Xima\XimaTypo3ContentPlanner\Configuration::FEATURE_NOTIFICATION_CONTENT_CHANGED})
 * deliberately lives in the caller (DataHandlerHook), not here, matching how every other
 * table/feature gate in that hook is already structured - this class assumes it is only called
 * when the feature is on.
 *
 * Performance (issue #309's "no DataHandler overhead for records without watchers, early return"
 * acceptance criterion): {@see WatcherService::getActiveWatchers()} - a single indexed query - is
 * the *only* thing queried for a record nobody watches. The notification payload (which itself
 * resolves the record's title via another query) is built, and {@see NotificationDispatcher} is
 * called, only once that check has already found at least one watcher.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
readonly class ContentChangeNotificationService
{
    public function __construct(
        private WatcherService $watcherService,
        private NotificationDispatcher $notificationDispatcher,
        private NotificationPayloadFactory $payloadFactory,
        private RecordRepository $recordRepository,
    ) {}

    /**
     * @throws Exception
     */
    public function recordChange(string $table, int $uid, ?int $actorUid): void
    {
        foreach ($this->resolveTargets($table, $uid) as [$targetTable, $targetUid]) {
            $this->notifyTarget($targetTable, $targetUid, $actorUid);
        }
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function resolveTargets(string $table, int $uid): array
    {
        $targets = [[$table, $uid]];

        // Resolving the parent page costs a query, and it is only worth paying when pages can
        // carry watchers at all. Without this guard every single tt_content save paid for it,
        // including on installations that do not track pages.
        if ('tt_content' === $table && $this->watcherService->isWatchable('pages')) {
            $pageUid = $this->recordRepository->findPidByUid($table, $uid);
            if (null !== $pageUid) {
                $targets[] = ['pages', $pageUid];
            }
        }

        return $targets;
    }

    /**
     * @throws Exception
     */
    private function notifyTarget(string $table, int $uid, ?int $actorUid): void
    {
        if (!$this->watcherService->isWatchable($table)) {
            return;
        }

        // The cheap, indexed watcher lookup this class's whole "no overhead for unwatched
        // records" guarantee rests on - everything below this line (payload construction,
        // dispatch, the database write) only runs once it found at least one watcher.
        if ([] === $this->watcherService->getActiveWatchers($table, $uid)) {
            return;
        }

        $this->notificationDispatcher->dispatch(
            NotificationEventType::ContentChanged,
            $table,
            $uid,
            $actorUid,
            $this->payloadFactory->forContentChanged($table, $uid, $actorUid),
        );
    }
}
