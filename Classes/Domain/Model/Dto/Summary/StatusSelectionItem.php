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
 * StatusSelectionItem.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class StatusSelectionItem
{
    public function __construct(
        public StatusSummary $status,
        public bool $current,
    ) {}

    /**
     * Unlike the backend menus, which drop the active status because picking it again is
     * a no-op, the API reports it and flags it — a consumer needs it to render the
     * selected entry.
     *
     * @return array{uid: int, title: string, colorName: string, colorHex: string|null, iconIdentifier: string, current: bool}
     */
    public function toArray(): array
    {
        return $this->status->toArray() + ['current' => $this->current];
    }
}
