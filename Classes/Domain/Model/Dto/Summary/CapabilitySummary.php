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
 * CapabilitySummary.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class CapabilitySummary
{
    /**
     * What the current backend user may do with this record. Advisory only — a consumer
     * hiding a button is a convenience, never the enforcement, which happens again in
     * every write endpoint.
     *
     * There is deliberately no "may view comments" flag: the extension has no such
     * permission, so it could only ever be true, and a field that never varies suggests
     * a check that does not exist. Being able to read a record's comments follows from
     * the visibility check already applied to the whole request.
     */
    public function __construct(
        public bool $canChangeStatus,
        public bool $canUnsetStatus,
        public bool $canComment,
    ) {}

    /**
     * @return array{canChangeStatus: bool, canUnsetStatus: bool, canComment: bool}
     */
    public function toArray(): array
    {
        return [
            'canChangeStatus' => $this->canChangeStatus,
            'canUnsetStatus' => $this->canUnsetStatus,
            'canComment' => $this->canComment,
        ];
    }
}
