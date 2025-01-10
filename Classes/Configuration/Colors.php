<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Configuration;

class Colors
{
    final public const STATUS_COLOR_BLACK = 'black';
    final public const STATUS_COLOR_BLUE = 'blue';
    final public const STATUS_COLOR_GREEN = 'green';
    final public const STATUS_COLOR_YELLOW = 'yellow';
    final public const STATUS_COLOR_RED = 'red';
    final public const STATUS_COLOR_GRAY = 'gray';
    final public const STATUS_COLOR_PURPLE = 'purple';
    final public const STATUS_COLOR_ORANGE = 'orange';

    final public const STATUS_COLORS = [
        self::STATUS_COLOR_BLACK,
        self::STATUS_COLOR_BLUE,
        self::STATUS_COLOR_GREEN,
        self::STATUS_COLOR_YELLOW,
        self::STATUS_COLOR_RED,
        self::STATUS_COLOR_GRAY,
        self::STATUS_COLOR_PURPLE,
        self::STATUS_COLOR_ORANGE,
    ];

    private const COLOR_CODES = [
        self::STATUS_COLOR_RED => '250,136,147',
        self::STATUS_COLOR_BLUE => '100,187,200',
        self::STATUS_COLOR_YELLOW => '255,205,117',
        self::STATUS_COLOR_GREEN => '106,158,113',
        self::STATUS_COLOR_PURPLE => '94,53,177',
        self::STATUS_COLOR_ORANGE => '245,124,0',
    ];

    public static function get(string $colorCode, bool $transparency = false): string
    {
        if (!in_array($colorCode, self::STATUS_COLORS)) {
            throw new \InvalidArgumentException('Invalid color code', 2653877737);
        }

        if (!array_key_exists($colorCode, self::COLOR_CODES)) {
            return $colorCode;
        }

        $key = $transparency ? 'rgba' : 'rgb';
        return $key . '(' . self::COLOR_CODES[$colorCode] . ($transparency ? ', 0.5' : '') . ')';
    }
}
