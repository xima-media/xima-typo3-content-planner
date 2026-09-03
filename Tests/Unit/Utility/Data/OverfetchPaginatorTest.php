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
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\PaginatedResult;
use Xima\XimaTypo3ContentPlanner\Utility\Data\OverfetchPaginator;

use function array_slice;

/**
 * OverfetchPaginatorTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class OverfetchPaginatorTest extends TestCase
{
    #[Test]
    public function returnsAllRowsAndNoMoreWhenFewerRowsThanPageSize(): void
    {
        $result = OverfetchPaginator::paginate([1, 2, 3], 20, static fn (int $row): bool => true);

        self::assertSame([1, 2, 3], $result->items);
        self::assertFalse($result->hasMore);
    }

    #[Test]
    public function hasMoreIsFalseWhenExactlyPageSizeVisibleRowsExist(): void
    {
        $result = OverfetchPaginator::paginate([1, 2, 3], 3, static fn (int $row): bool => true);

        self::assertSame([1, 2, 3], $result->items);
        self::assertFalse($result->hasMore);
    }

    #[Test]
    public function hasMoreIsTrueWhenMoreVisibleRowsExistBeyondPageSize(): void
    {
        $result = OverfetchPaginator::paginate([1, 2, 3, 4, 5], 3, static fn (int $row): bool => true);

        self::assertSame([1, 2, 3], $result->items);
        self::assertTrue($result->hasMore);
    }

    #[Test]
    public function invisibleRowsDoNotCountTowardsPageSizeOrHasMore(): void
    {
        // 10 raw rows, but only rows 1, 3, 5 are "visible" (e.g. permission-denied for the rest).
        // A naive SQL-level LIMIT of 3 would have truncated before permission filtering and left
        // fewer (or zero) visible rows even though more exist. Over-fetching all 10 first and then
        // filtering here must still surface every visible row up to the page size.
        $isVisible = static fn (int $row): bool => 0 === $row % 2;
        $rows = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

        $result = OverfetchPaginator::paginate($rows, 3, $isVisible);

        self::assertSame([2, 4, 6], $result->items);
        self::assertTrue($result->hasMore);
    }

    #[Test]
    public function hasMoreIsFalseWhenVisibleRowsExactlyFillPageDespiteHiddenRowsAfter(): void
    {
        // Only 3 visible rows total (2, 4, 6); the trailing invisible rows must not flip hasMore.
        $isVisible = static fn (int $row): bool => 0 === $row % 2 && $row <= 6;
        $rows = [1, 2, 3, 4, 5, 6, 7, 8, 9];

        $result = OverfetchPaginator::paginate($rows, 3, $isVisible);

        self::assertSame([2, 4, 6], $result->items);
        self::assertFalse($result->hasMore);
    }

    #[Test]
    public function emptyRowsProduceEmptyResultWithoutMore(): void
    {
        $result = OverfetchPaginator::paginate([], 20, static fn (int $row): bool => true);

        self::assertSame([], $result->items);
        self::assertFalse($result->hasMore);
    }

    #[Test]
    public function zeroPageSizeMarksHasMoreAssoonAsAVisibleRowExists(): void
    {
        $result = OverfetchPaginator::paginate([1], 0, static fn (int $row): bool => true);

        self::assertSame([], $result->items);
        self::assertTrue($result->hasMore);
    }

    #[Test]
    public function pullsFurtherBatchesWhenAWholeWindowIsInvisible(): void
    {
        // The regression this guards: 250 consecutive invisible rows precede the first visible
        // one. A single over-fetched window of 100 would end here with an empty page and
        // hasMore=false, wrongly reporting "nothing to show" while matches exist further down.
        $source = array_merge(range(1, 250), [1000, 1001, 1002]);
        $isVisible = static fn (int $row): bool => $row >= 1000;

        // What a single window produced before batching: an empty page that claims there is
        // nothing more, even though three visible rows sit just past the window.
        $singleWindow = OverfetchPaginator::paginate(array_slice($source, 0, 100), 2, $isVisible);
        self::assertSame([], $singleWindow->items);
        self::assertFalse($singleWindow->hasMore);

        $result = self::paginateOver($source, 2, 100, 10, $isVisible);

        self::assertSame([1000, 1001], $result->items);
        self::assertTrue($result->hasMore);
    }

    #[Test]
    public function stopsAtMaxBatchesInsteadOfScanningUnbounded(): void
    {
        // Nothing is ever visible, so the loop must be stopped by the batch bound rather than
        // walking the whole source. Since the scan was capped rather than exhausted (every batch
        // came back full-size), hasMore must conservatively report true.
        $batchesFetched = 0;
        $fetchBatch = static function (int $offset) use (&$batchesFetched): array {
            ++$batchesFetched;

            return range($offset + 1, $offset + 10);
        };

        $result = OverfetchPaginator::paginateBatched($fetchBatch, 5, 10, 3, static fn (int $row): bool => false);

        self::assertSame([], $result->items);
        self::assertTrue($result->hasMore);
        self::assertSame(3, $batchesFetched);
    }

    #[Test]
    public function stopsEarlyWhenTheSourceIsExhausted(): void
    {
        // A short batch means there is nothing left; the paginator must not ask for another one.
        $batchesFetched = 0;
        $fetchBatch = static function (int $offset) use (&$batchesFetched): array {
            ++$batchesFetched;

            return 0 === $offset ? [1, 2, 3] : [];
        };

        $result = OverfetchPaginator::paginateBatched($fetchBatch, 10, 10, 5, static fn (int $row): bool => true);

        self::assertSame([1, 2, 3], $result->items);
        self::assertFalse($result->hasMore);
        self::assertSame(1, $batchesFetched);
    }

    #[Test]
    public function reportsHasMoreForAVisibleRowFoundInALaterBatch(): void
    {
        // The page fills from the first batch, but the row that proves "there is more" only
        // shows up in the second one.
        $source = array_merge([1, 2], range(100, 197), [3]);
        $isVisible = static fn (int $row): bool => $row < 100;

        $result = self::paginateOver($source, 2, 100, 10, $isVisible);

        self::assertSame([1, 2], $result->items);
        self::assertTrue($result->hasMore);
    }

    /**
     * Serve $source through the batched API the way a paged SQL query would.
     *
     * @param list<int>           $source
     * @param callable(int): bool $isVisible
     *
     * @return PaginatedResult<int>
     */
    private static function paginateOver(array $source, int $pageSize, int $batchSize, int $maxBatches, callable $isVisible): PaginatedResult
    {
        return OverfetchPaginator::paginateBatched(
            static fn (int $offset): array => array_values(array_slice($source, $offset, $batchSize)),
            $pageSize,
            $batchSize,
            $maxBatches,
            $isVisible,
        );
    }
}
