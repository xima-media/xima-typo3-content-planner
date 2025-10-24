<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Configuration;

use InvalidArgumentException;

use function array_key_exists;
use function in_array;

/**
 * Colors.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
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
            throw new InvalidArgumentException('Invalid color code', 2653877737);
        }

        if (!array_key_exists($colorCode, self::COLOR_CODES)) {
            return $colorCode;
        }

        $key = $transparency ? 'rgba' : 'rgb';

        return $key.'('.self::COLOR_CODES[$colorCode].($transparency ? ', 0.5' : '').')';
    }
}
