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

class VisibilityUtility
{
    public static function checkContentStatusVisibility(): bool
    {
        // check permission
        if (!$GLOBALS['BE_USER']->isAdmin() && !$GLOBALS['BE_USER']->check('custom_options', 'tx_ximatypo3contentplanner:content-status')) {
            return false;
        }

        // check user setting
        if (($GLOBALS['BE_USER']->user['tx_ximatypo3contentplanner_hide'] ?? 0) === 1) {
            return false;
        }

        return true;
    }
}
