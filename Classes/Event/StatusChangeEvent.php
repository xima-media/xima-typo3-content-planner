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

namespace Xima\XimaTypo3ContentPlanner\Event;

use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;

/**
* StatusChangeEvent
*
* Use this event to influence into the status change.
* Therefore, it is possible to change fhe fieldArray of the corresponding record.
*
* Example usage:
*```php
* $fieldArray = $event->getFieldArray();
*
* if ($event->getNewStatus() && $event->getNewStatus()->getTitle() === 'Final review') {
*   // Assign the record to a specific user, e.g. the chief editor
*   $fieldArray['tx_ximatypo3contentplanner_assignee'] = 42;
* }
*
* $event->setFieldArray($fieldArray);
* ```
*/
class StatusChangeEvent
{
    final public const NAME = 'xima_typo3_content_planner.status.change';

    /**
    * @param array<string, mixed> $fieldArray
    */
    public function __construct(
        protected string $table,
        protected int $uid,
        protected array $fieldArray,
        protected ?Status $previousStatus,
        protected ?Status $newStatus
    ) {}

    public function getTable(): string
    {
        return $this->table;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    /**
    * @return array<string, mixed>
    */
    public function getFieldArray(): array
    {
        return $this->fieldArray;
    }

    /**
    * @param array<string, mixed> $fieldArray
    */
    public function setFieldArray(array $fieldArray): void
    {
        $this->fieldArray = $fieldArray;
    }

    /**
    * @return \Xima\XimaTypo3ContentPlanner\Domain\Model\Status|null
    */
    public function getPreviousStatus(): ?Status
    {
        return $this->previousStatus;
    }

    /**
    * @return \Xima\XimaTypo3ContentPlanner\Domain\Model\Status|null
    */
    public function getNewStatus(): ?Status
    {
        return $this->newStatus;
    }

    /**
    * @param \Xima\XimaTypo3ContentPlanner\Domain\Model\Status|null $newStatus
    */
    public function setNewStatus(?Status $newStatus): void
    {
        $this->newStatus = $newStatus;
    }
}
