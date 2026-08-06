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
 * AssigneeSummary.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class AssigneeSummary
{
    public function __construct(
        public int $uid,
        public string $displayName,
    ) {}

    /**
     * @return array{uid: int, displayName: string}
     */
    public function toArray(): array
    {
        return [
            'uid' => $this->uid,
            'displayName' => $this->displayName,
        ];
    }
}
