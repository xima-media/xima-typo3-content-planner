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

namespace Xima\XimaTypo3ContentPlanner\Service\Notification\Retention;

use Doctrine\DBAL\Exception;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\RetentionRunResult;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, NotificationRepository, RecordRepository, WatcherRepository};

/**
 * NotificationRetentionService.
 *
 * All actual work behind `content-planner:notification:cleanup` (issue #304), following the same
 * "thin command, fat service" convention as
 * {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\Digest\DigestService} for issue #302's
 * email digest command.
 *
 * Runs four independent retention/orphan rules per invocation:
 *
 * -   delete *read* notifications older than a configurable number of days
 * -   delete *unread* notifications older than a (longer, by default) configurable number of days
 * -   delete watcher/notification rows whose referenced record no longer exists
 * -   delete watcher/notification rows owned by a deleted/disabled backend user
 *
 * `$dryRun` runs every rule's matching logic but only counts what it would have deleted -
 * see {@see NotificationRepository::deleteOlderThan()} and friends, which take the flag through
 * to a shared count-instead-of-delete code path so the two modes can never drift apart.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class NotificationRetentionService
{
    private const SECONDS_PER_DAY = 86400;

    /**
     * Matches {@see NotificationRepository}'s DELETE_CHUNK_SIZE convention: keeps the
     * `filterActiveUids()` IN-clause bounded even though the candidate set itself (distinct
     * backend users referenced by notifications/watchers) is not expected to be large.
     */
    private const FILTER_CHUNK_SIZE = 500;

    public function __construct(
        private NotificationRepository $notificationRepository,
        private WatcherRepository $watcherRepository,
        private RecordRepository $recordRepository,
        private BackendUserRepository $backendUserRepository,
    ) {}

    /**
     * @throws Exception
     */
    public function run(int $readRetentionDays, int $unreadRetentionDays, bool $dryRun): RetentionRunResult
    {
        $now = time();

        $readDeleted = $this->notificationRepository->deleteOlderThan(
            true,
            $now - $readRetentionDays * self::SECONDS_PER_DAY,
            $dryRun,
        );
        $unreadDeleted = $this->notificationRepository->deleteOlderThan(
            false,
            $now - $unreadRetentionDays * self::SECONDS_PER_DAY,
            $dryRun,
        );

        [$orphanedNotificationsForRecords, $orphanedWatchersForRecords] = $this->cleanupOrphanedRecords($dryRun);
        [$orphanedNotificationsForUsers, $orphanedWatchersForUsers] = $this->cleanupOrphanedBackendUsers($dryRun);

        return new RetentionRunResult(
            $readDeleted,
            $unreadDeleted,
            $orphanedNotificationsForRecords + $orphanedNotificationsForUsers,
            $orphanedWatchersForRecords + $orphanedWatchersForUsers,
            $dryRun,
        );
    }

    /**
     * Deletes watcher/notification rows whose `(tablename, record_uid)` no longer resolves to a
     * live record - checked once per distinct pair referenced by either table (not once per row),
     * via {@see RecordRepository::existingUids()}.
     *
     * @return array{0: int, 1: int} [notificationsDeleted, watchersDeleted]
     *
     * @throws Exception
     */
    private function cleanupOrphanedRecords(bool $dryRun): array
    {
        $recordUidsByTable = $this->groupDistinctRecordUidsByTable(
            $this->notificationRepository->findDistinctTableRecordPairs(),
            $this->watcherRepository->findDistinctTableRecordPairs(),
        );

        $notificationsDeleted = 0;
        $watchersDeleted = 0;

        foreach ($recordUidsByTable as $table => $recordUids) {
            $orphanedUids = array_values(array_diff($recordUids, $this->recordRepository->existingUids($table, $recordUids)));
            if ([] === $orphanedUids) {
                continue;
            }

            $notificationsDeleted += $this->notificationRepository->deleteForTableAndRecordUids($table, $orphanedUids, $dryRun);
            $watchersDeleted += $this->watcherRepository->deleteForTableAndRecordUids($table, $orphanedUids, $dryRun);
        }

        return [$notificationsDeleted, $watchersDeleted];
    }

    /**
     * Deletes watcher/notification rows owned by a backend user that is now deleted, disabled, or
     * simply gone - checked once per distinct recipient/watcher uid referenced by either table.
     *
     * @return array{0: int, 1: int} [notificationsDeleted, watchersDeleted]
     *
     * @throws Exception
     */
    private function cleanupOrphanedBackendUsers(bool $dryRun): array
    {
        $referencedUids = array_values(array_unique([
            ...$this->notificationRepository->findDistinctBackendUsers(),
            ...$this->watcherRepository->findDistinctBackendUsers(),
        ]));

        if ([] === $referencedUids) {
            return [0, 0];
        }

        $activeUids = array_unique(array_merge(
            ...array_map(
                fn (array $chunk): array => $this->backendUserRepository->filterActiveUids($chunk),
                array_chunk($referencedUids, self::FILTER_CHUNK_SIZE),
            ),
        ));

        $orphanedUids = array_values(array_diff($referencedUids, $activeUids));
        if ([] === $orphanedUids) {
            return [0, 0];
        }

        return [
            $this->notificationRepository->deleteForBackendUsers($orphanedUids, $dryRun),
            $this->watcherRepository->deleteForBackendUsers($orphanedUids, $dryRun),
        ];
    }

    /**
     * @param list<array{tablename: string, record_uid: int}> $notificationPairs
     * @param list<array{tablename: string, record_uid: int}> $watcherPairs
     *
     * @return array<string, list<int>>
     */
    private function groupDistinctRecordUidsByTable(array $notificationPairs, array $watcherPairs): array
    {
        $grouped = [];
        foreach ([...$notificationPairs, ...$watcherPairs] as $pair) {
            // Keying by record_uid also dedupes a pair present in both the notification and
            // watcher table.
            $grouped[$pair['tablename']][$pair['record_uid']] = $pair['record_uid'];
        }

        return array_map(array_values(...), $grouped);
    }
}
