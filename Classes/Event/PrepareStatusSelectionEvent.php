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
* PrepareStatusSelectionEvent
*
* Use this event to influence the status selection in the backend regarding multiple factors (e.g. current state, current record or context class).
* Therefore, it is possible to implement some kind of simple workflow for status changes.
*
* Example usage:
*```php
* $selection = $event->getSelection();
*
* if ($event->getCurrentStatus() && $event->getCurrentStatus()->getTitle() === 'Needs review') {
*  $targetStatus = $this->statusRepository->findOneByTitle('Pending');
*
*  if ($targetStatus) {
*      unset($selection[$targetStatus->getUid()]);
*  }
* }
*
* $event->setSelection($selection);
* ```
*/
class PrepareStatusSelectionEvent
{
    final public const NAME = 'xima_typo3_content_planner.status.prepare_selection';

    /**
    * @param string $table
    * @param int|null $uid
    * @param object $context
    * @param array<string, mixed> $selection
    * @param Status|null $currentStatus
    */
    public function __construct(
        protected string $table,
        protected ?int $uid,
        protected object $context,
        protected array $selection,
        protected ?Status $currentStatus,
    ) {}

    public function getTable(): string
    {
        return $this->table;
    }

    public function getUid(): ?int
    {
        return $this->uid;
    }

    /**
    * @return array<string, mixed>
    */
    public function getSelection(): array
    {
        return $this->selection;
    }

    /**
    * @param array<string, mixed> $selection
    */
    public function setSelection(array $selection): void
    {
        $this->selection = $selection;
    }

    /**
    * @return \Xima\XimaTypo3ContentPlanner\Domain\Model\Status|null
    */
    public function getCurrentStatus(): ?Status
    {
        return $this->currentStatus;
    }

    public function getContext(): object
    {
        return $this->context;
    }
}
