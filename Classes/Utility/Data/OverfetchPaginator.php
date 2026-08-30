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

use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\PaginatedResult;

use function count;

/**
 * OverfetchPaginator.
 *
 * Applies a visibility predicate (typically a permission check) to a row set
 * that was already fetched with a limit larger than the requested page size,
 * then truncates it to that page size.
 *
 * This exists because a caller like
 * {@see \Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository::findAllByFilter()}
 * cannot express "visible to the current backend user" as a SQL predicate
 * shared across differently-shaped tracked tables. Filtering by permission
 * has to happen in PHP, after the query, which means the SQL `LIMIT` can no
 * longer be the page size: it has to over-fetch so that permission filtering
 * still leaves enough visible rows to fill a page. The over-fetch bound is the
 * caller's responsibility (see `FILTER_OVERFETCH_FACTOR`/`FILTER_OVERFETCH_CAP`
 * on `RecordRepository`).
 *
 * A single over-fetched window is not enough on its own: if more consecutive
 * invisible rows precede the first visible one than the window holds, the page
 * comes back short and `hasMore` under-reports. {@see self::paginateBatched()}
 * therefore keeps pulling further windows until the page is full or the source
 * is exhausted, so the result no longer depends on how the invisible rows
 * happen to be distributed. {@see self::paginate()} remains available for
 * callers that already hold the complete row set.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class OverfetchPaginator
{
    /**
     * Paginate a row set that is already complete.
     *
     * @template T
     *
     * @param list<T>           $rows      over-fetched rows, already ordered
     * @param callable(T): bool $isVisible
     *
     * @return PaginatedResult<T>
     */
    public static function paginate(array $rows, int $pageSize, callable $isVisible): PaginatedResult
    {
        return self::paginateBatched(
            static fn (int $offset): array => 0 === $offset ? $rows : [],
            $pageSize,
            \PHP_INT_MAX,
            1,
            $isVisible,
        );
    }

    /**
     * Pull successive windows until $pageSize visible rows are collected, the
     * source runs out, or $maxBatches windows have been inspected.
     *
     * $fetchBatch receives the row offset to start at and must return at most
     * $batchSize rows in the caller's stable sort order; a shorter result is
     * taken to mean the source is exhausted. $maxBatches bounds the work so a
     * pathological ratio of invisible rows cannot turn one request into an
     * unbounded scan.
     *
     * @template T
     *
     * @param callable(int): list<T> $fetchBatch
     * @param callable(T): bool      $isVisible
     *
     * @return PaginatedResult<T>
     */
    public static function paginateBatched(
        callable $fetchBatch,
        int $pageSize,
        int $batchSize,
        int $maxBatches,
        callable $isVisible,
    ): PaginatedResult {
        $items = [];
        $offset = 0;

        for ($batch = 0; $batch < $maxBatches; ++$batch) {
            $rows = $fetchBatch($offset);

            if ([] === $rows) {
                break;
            }

            foreach ($rows as $row) {
                if (!$isVisible($row)) {
                    continue;
                }

                if (count($items) >= $pageSize) {
                    return new PaginatedResult($items, true);
                }

                $items[] = $row;
            }

            if (count($rows) < $batchSize) {
                break;
            }

            $offset += $batchSize;
        }

        return new PaginatedResult($items, false);
    }
}
