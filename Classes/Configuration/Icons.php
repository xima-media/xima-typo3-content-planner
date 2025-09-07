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

namespace Xima\XimaTypo3ContentPlanner\Configuration;

/**
 * Icons.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
class Icons
{
    final public const STATUS_ICON_FLAG = 'flag';
    final public const STATUS_ICON_HEART = 'heart';
    final public const STATUS_ICON_TAG = 'tag';
    final public const STATUS_ICON_STAR = 'star';
    final public const STATUS_ICON_INFO = 'info';
    final public const STATUS_ICON_CERTIFICATE = 'certificate';
    final public const STATUS_ICON_EXCLAMATION = 'exclamation';
    final public const STATUS_ICON_ROCKET = 'rocket';
    final public const STATUS_ICON_THUMBTACK = 'thumbtack';

    final public const STATUS_ICONS = [
        self::STATUS_ICON_FLAG,
        self::STATUS_ICON_HEART,
        self::STATUS_ICON_TAG,
        self::STATUS_ICON_STAR,
        self::STATUS_ICON_INFO,
        self::STATUS_ICON_CERTIFICATE,
        self::STATUS_ICON_EXCLAMATION,
        self::STATUS_ICON_ROCKET,
        self::STATUS_ICON_THUMBTACK,
    ];
}
