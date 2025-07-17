<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "xima_typo3_content_planner".
 *
 * Copyright (C) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder;

use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;

interface SelectionInterface
{
    /**
    * @param array<string|int, mixed> $selectionEntriesToAdd
    * @param array<int>|int|null $uid
    * @param array<string, mixed>|bool|null $record
    */
    public function addStatusItemToSelection(
        array &$selectionEntriesToAdd,
        Status $status,
        Status|int|null $currentStatus = null,
        ?string $table = null,
        array|int|null $uid = null,
        array|bool|null $record = null
    ): void;

    /**
    * @param array<string, mixed> $selectionEntriesToAdd
    * @param array<int,int>|int|null $uid
    * @param array<string, mixed>|bool|null $record
    */
    public function addStatusResetItemToSelection(
        array &$selectionEntriesToAdd,
        ?string $table = null,
        array|int|null $uid = null,
        array|bool|null $record = null
    ): void;

    /**
    * @param array<string, mixed> $selectionEntriesToAdd
    */
    public function addDividerItemToSelection(array &$selectionEntriesToAdd): void;

    /**
    * @param array<string, mixed> $selectionEntriesToAdd
    * @param array<string, mixed> $record
    */
    public function addAssigneeItemToSelection(
        array &$selectionEntriesToAdd,
        array $record,
        ?string $table = null,
        ?int $uid = null
    ): void;

    /**
    * @param array<string, mixed> $selectionEntriesToAdd
    * @param array<string, mixed> $record
    */
    public function addCommentsItemToSelection(
        array &$selectionEntriesToAdd,
        array $record,
        ?string $table = null,
        ?int $uid = null
    ): void;
}
