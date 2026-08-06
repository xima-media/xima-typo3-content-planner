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

use function array_map;
use function array_values;

/**
 * StatusSelection.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class StatusSelection
{
    /**
     * @param StatusSelectionItem[] $items
     */
    public function __construct(
        public string $table,
        public int $uid,
        public array $items,
        public bool $canUnset,
    ) {}

    /**
     * @return array{table: string, uid: int, items: list<array<string, mixed>>, canUnset: bool}
     */
    public function toArray(): array
    {
        return [
            'table' => $this->table,
            'uid' => $this->uid,
            'items' => array_map(
                static fn (StatusSelectionItem $item): array => $item->toArray(),
                array_values($this->items),
            ),
            'canUnset' => $this->canUnset,
        ];
    }
}
