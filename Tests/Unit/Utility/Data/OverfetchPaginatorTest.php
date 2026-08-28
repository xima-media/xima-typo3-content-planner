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
use Xima\XimaTypo3ContentPlanner\Utility\Data\OverfetchPaginator;

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
}
