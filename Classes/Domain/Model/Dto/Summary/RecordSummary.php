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
 * RecordSummary.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class RecordSummary
{
    public function __construct(
        public string $table,
        public int $uid,
        public ?StatusSummary $status,
        public ?AssigneeSummary $assignee,
        public CommentSummary $comments,
        public CapabilitySummary $capabilities,
    ) {}

    /**
     * The API's per-record shape. Kept free of rendered markup on purpose — StatusItem
     * remains the HTML-bearing DTO for the backend views.
     *
     * @return array{
     *     table: string,
     *     uid: int,
     *     status: array{uid: int, title: string, colorName: string, colorHex: string|null, iconIdentifier: string}|null,
     *     assignee: array{uid: int, displayName: string}|null,
     *     comments: array{total: int, todoTotal: int, todoResolved: int},
     *     capabilities: array{canChangeStatus: bool, canUnsetStatus: bool, canComment: bool}
     * }
     */
    public function toArray(): array
    {
        return [
            'table' => $this->table,
            'uid' => $this->uid,
            'status' => $this->status?->toArray(),
            'assignee' => $this->assignee?->toArray(),
            'comments' => $this->comments->toArray(),
            'capabilities' => $this->capabilities->toArray(),
        ];
    }
}
