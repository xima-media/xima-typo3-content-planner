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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Utility\Data;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Utility\Data\WatchedRecordsFilter;

/**
 * WatchedRecordsFilterTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class WatchedRecordsFilterTest extends TestCase
{
    #[Test]
    public function nullWatchedRecordsMeansTheFilterIsInactiveAndEveryRecordPasses(): void
    {
        self::assertTrue(WatchedRecordsFilter::isWatched(['tablename' => 'pages', 'uid' => 1], null));
    }

    #[Test]
    public function recordPassesWhenItsUidIsInTheWatchedListForItsTable(): void
    {
        $watchedRecords = ['pages' => [1, 3]];

        self::assertTrue(WatchedRecordsFilter::isWatched(['tablename' => 'pages', 'uid' => 1], $watchedRecords));
    }

    #[Test]
    public function recordFailsWhenItsUidIsNotInTheWatchedListForItsTable(): void
    {
        $watchedRecords = ['pages' => [1, 3]];

        self::assertFalse(WatchedRecordsFilter::isWatched(['tablename' => 'pages', 'uid' => 2], $watchedRecords));
    }

    #[Test]
    public function recordFailsWhenItsTableHasNoWatchedEntriesAtAll(): void
    {
        // The watched-by-me map only has entries for 'tt_content'; a 'pages' record must not
        // accidentally pass just because some other table is being watched.
        $watchedRecords = ['tt_content' => [1]];

        self::assertFalse(WatchedRecordsFilter::isWatched(['tablename' => 'pages', 'uid' => 1], $watchedRecords));
    }

    #[Test]
    public function everyRecordFailsWhenTheUserWatchesNothingAtAll(): void
    {
        self::assertFalse(WatchedRecordsFilter::isWatched(['tablename' => 'pages', 'uid' => 1], []));
    }

    #[Test]
    public function matchingIsScopedPerTableSoTheSameUidInAnotherTableDoesNotMatch(): void
    {
        $watchedRecords = ['tt_content' => [1]];

        self::assertFalse(WatchedRecordsFilter::isWatched(['tablename' => 'pages', 'uid' => 1], $watchedRecords));
        self::assertTrue(WatchedRecordsFilter::isWatched(['tablename' => 'tt_content', 'uid' => 1], $watchedRecords));
    }
}
