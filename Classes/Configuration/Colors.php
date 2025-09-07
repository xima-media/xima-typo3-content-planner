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
 * Colors.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
class Colors
{
    final public const STATUS_COLOR_BLACK = 'black';
    final public const STATUS_COLOR_BLUE = 'blue';
    final public const STATUS_COLOR_GREEN = 'green';
    final public const STATUS_COLOR_YELLOW = 'yellow';
    final public const STATUS_COLOR_RED = 'red';
    final public const STATUS_COLOR_PURPLE = 'purple';
    final public const STATUS_COLOR_ORANGE = 'orange';

    final public const STATUS_COLORS = [
        self::STATUS_COLOR_BLACK,
        self::STATUS_COLOR_BLUE,
        self::STATUS_COLOR_GREEN,
        self::STATUS_COLOR_YELLOW,
        self::STATUS_COLOR_RED,
        self::STATUS_COLOR_PURPLE,
        self::STATUS_COLOR_ORANGE,
    ];

    private const COLOR_CODES = [
        self::STATUS_COLOR_BLACK => '144,164,174',
        self::STATUS_COLOR_RED => '250,136,147',
        self::STATUS_COLOR_BLUE => '100,187,200',
        self::STATUS_COLOR_YELLOW => '255,205,117',
        self::STATUS_COLOR_GREEN => '106,158,113',
        self::STATUS_COLOR_PURPLE => '92,107,192',
        self::STATUS_COLOR_ORANGE => '255,112,67',
    ];

    public static function get(string $colorCode, bool $transparency = false): string
    {
        if (!in_array($colorCode, self::STATUS_COLORS, true)) {
            throw new \InvalidArgumentException('Invalid color code', 2653877737);
        }

        if (!array_key_exists($colorCode, self::COLOR_CODES)) {
            return $colorCode;
        }

        $key = $transparency ? 'rgba' : 'rgb';
        return $key . '(' . self::COLOR_CODES[$colorCode] . ($transparency ? ', 0.5' : '') . ')';
    }
}
