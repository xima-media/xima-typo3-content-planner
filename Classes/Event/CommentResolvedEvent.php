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

namespace Xima\XimaTypo3ContentPlanner\Event;

/**
 * CommentResolvedEvent.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class CommentResolvedEvent
{
    public function __construct(
        private string $table,
        private int $recordUid,
        private int $commentUid,
        private int $resolvedByUid,
    ) {}

    public function getTable(): string
    {
        return $this->table;
    }

    public function getRecordUid(): int
    {
        return $this->recordUid;
    }

    public function getCommentUid(): int
    {
        return $this->commentUid;
    }

    public function getResolvedByUid(): int
    {
        return $this->resolvedByUid;
    }
}
