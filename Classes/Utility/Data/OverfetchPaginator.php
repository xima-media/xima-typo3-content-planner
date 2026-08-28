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
 * `hasMore` is therefore a pragmatic, bounded signal: it is accurate only
 * within the over-fetched window handed to {@see self::paginate()}. Visible
 * rows that exist beyond that window (because the bound was too small) are
 * not accounted for and `hasMore` would under-report in that case. Widen the
 * caller's over-fetch bound if this proves insufficient in practice.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class OverfetchPaginator
{
    /**
     * @template T
     *
     * @param list<T>           $rows      over-fetched rows, already ordered
     * @param callable(T): bool $isVisible
     *
     * @return PaginatedResult<T>
     */
    public static function paginate(array $rows, int $pageSize, callable $isVisible): PaginatedResult
    {
        $items = [];
        $hasMore = false;

        foreach ($rows as $row) {
            if (!$isVisible($row)) {
                continue;
            }

            if (count($items) >= $pageSize) {
                $hasMore = true;
                break;
            }

            $items[] = $row;
        }

        return new PaginatedResult($items, $hasMore);
    }
}
