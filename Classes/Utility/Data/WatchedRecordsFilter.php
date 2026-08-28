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

namespace Xima\XimaTypo3ContentPlanner\Utility\Data;

use function in_array;

/**
 * WatchedRecordsFilter.
 *
 * Applies the "Watched by me" visibility predicate for
 * {@see \Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository::findAllByFilter()}: a
 * record passes only if it is one of the caller's actively watched records, as supplied by
 * {@see \Xima\XimaTypo3ContentPlanner\Service\WatcherService::getWatchedRecords()} and grouped by
 * table name to match the UNION query's `tablename` column. `null` means the filter is inactive
 * (no "Watched by me" restriction requested), so every record passes.
 *
 * Kept as its own small pure class, mirroring {@see OverfetchPaginator}, so the predicate is
 * unit-testable without a database or TYPO3 bootstrap.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class WatchedRecordsFilter
{
    /**
     * @param array<string, mixed>          $record         must carry 'tablename' and 'uid'
     * @param array<string, list<int>>|null $watchedRecords table => watched record UIDs; null disables the filter
     */
    public static function isWatched(array $record, ?array $watchedRecords): bool
    {
        if (null === $watchedRecords) {
            return true;
        }

        $table = (string) $record['tablename'];
        $uid = (int) $record['uid'];

        return in_array($uid, $watchedRecords[$table] ?? [], true);
    }
}
