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
 * CommentFilter.
 *
 * What the filter column of the record modal asks for: open comments (default), only those with
 * to-dos, or all including resolved ones. The two flags the repository reads are derived from the
 * one `show` choice, and stay accepted on their own for links that carry them.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class CommentFilter
{
    public const SHOW_OPEN = 'open';
    public const SHOW_TODO = 'todo';
    public const SHOW_ALL = 'all';

    public function __construct(
        public string $show,
        public bool $showResolved,
        public bool $showTodo,
    ) {}

    /**
     * @param array<string, mixed> $queryParams
     */
    public static function fromQueryParams(array $queryParams): self
    {
        $show = (string) ($queryParams['show'] ?? '');
        $showTodo = self::SHOW_TODO === $show || (bool) ($queryParams['showTodoComments'] ?? false);
        $showResolved = self::SHOW_ALL === $show || (bool) ($queryParams['showResolvedComments'] ?? false);

        return new self(
            match (true) {
                $showTodo => self::SHOW_TODO,
                $showResolved => self::SHOW_ALL,
                default => self::SHOW_OPEN,
            },
            $showResolved,
            $showTodo,
        );
    }
}
