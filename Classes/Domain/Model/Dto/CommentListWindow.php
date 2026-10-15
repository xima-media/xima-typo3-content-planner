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

use function array_filter;
use function array_slice;
use function array_values;
use function count;

/**
 * CommentListWindow.
 *
 * The part of a record's root comments the comments tab renders: only the resolved ones for the
 * "Resolved" choice, and at most {@see self::LIMIT} of them, the newest, until the reader asks for
 * the older ones. `$olderCount` is how many are left out, `$olderFirst` whether they would come
 * before the rendered ones (oldest first sort) or after them.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class CommentListWindow
{
    public const LIMIT = 20;

    /**
     * @param array<int, CommentItem> $comments
     */
    public function __construct(
        public array $comments,
        public int $olderCount,
        public bool $olderFirst,
    ) {}

    /**
     * @param array<int, CommentItem> $comments sorted the way they are shown
     */
    public static function of(array $comments, CommentFilter $filter, string $sortDirection): self
    {
        if ($filter->onlyResolved) {
            $comments = array_values(array_filter($comments, static fn (CommentItem $comment): bool => $comment->isResolved()));
        }

        $total = count($comments);
        if ($filter->loadAll || $total <= self::LIMIT) {
            return new self($comments, 0, false);
        }

        $olderFirst = 'ASC' === $sortDirection;

        return new self(
            $olderFirst ? array_slice($comments, -self::LIMIT) : array_slice($comments, 0, self::LIMIT),
            $total - self::LIMIT,
            $olderFirst,
        );
    }
}
