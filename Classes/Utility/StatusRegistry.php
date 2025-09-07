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

namespace Xima\XimaTypo3ContentPlanner\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;

/**
 * StatusRegistry.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
class StatusRegistry
{
    /**
    * @param array<string, mixed> $config
    */
    public function getStatus(array &$config): void
    {
        $statusRepository = GeneralUtility::makeInstance(StatusRepository::class);

        foreach ($statusRepository->findAll() as $status) {
            $config['items'][] = [
                $status->getTitle(),
                $status->getUid(),
                $status->getColoredIcon(),
            ];
        }
    }

    /**
    * @param array<string, mixed> $config
    */
    public function getStatusIcons(array &$config): void
    {
        foreach (Configuration\Icons::STATUS_ICONS as $icon) {
            $config['items'][] = [
                $icon,
                $icon,
                "$icon-black",
            ];
        }
    }

    /**
    * @param array<string, mixed> $config
    */
    public function getStatusColors(array &$config): void
    {
        foreach (Configuration\Colors::STATUS_COLORS as $color) {
            $config['items'][] = [
                $color,
                $color,
                "color-$color",
            ];
        }
    }
}
