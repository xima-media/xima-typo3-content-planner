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

namespace Xima\XimaTypo3ContentPlanner\Domain\Model\Dto;

/**
 * PaginatedResult.
 *
 * Generic envelope for a page of results plus whether more (visible) results
 * exist beyond it. Introduced for {@see \Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository::findAllByFilter()}
 * (CP-16) and intentionally kept table-/repository-agnostic so it can be
 * reused wherever a permission-filtered, over-fetched result set needs a
 * `hasMore` signal, e.g. lazily loaded aggregated child comments (CP-29).
 *
 * @template T
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class PaginatedResult
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public array $items,
        public bool $hasMore,
    ) {}
}
