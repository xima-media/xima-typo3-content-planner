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

namespace Xima\XimaTypo3ContentPlanner\Manager;

use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Event\PrepareStatusSelectionEvent;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;

/**
 * StatusSelectionManager.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
class StatusSelectionManager
{
    public function __construct(private readonly EventDispatcher $eventDispatcher) {}

    /**
    * @param array<string, mixed> $selection
    */
    public function prepareStatusSelection(object $context, string $table, ?int $uid, array &$selection, ?int $statusUid = null, ?Status $status = null): void
    {
        if ($statusUid !== null && $status === null) {
            $status = ContentUtility::getStatus($statusUid);
        }

        $event = $this->eventDispatcher->dispatch(new PrepareStatusSelectionEvent($table, $uid, $context, $selection, $status));
        $selection = $event->getSelection();
    }
}
