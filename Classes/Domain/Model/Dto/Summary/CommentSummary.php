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

namespace Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\Summary;

/**
 * CommentSummary.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class CommentSummary
{
    /**
     * $total counts open comments including replies, matching the backend badge, which
     * uses CommentRepository::countAllByRecord() with its defaults.
     */
    public function __construct(
        public int $total,
        public int $todoTotal,
        public int $todoResolved,
    ) {}

    /**
     * @return array{total: int, todoTotal: int, todoResolved: int}
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'todoTotal' => $this->todoTotal,
            'todoResolved' => $this->todoResolved,
        ];
    }
}
