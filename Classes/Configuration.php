<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner;

class Configuration
{
    final public const EXT_KEY = 'xima_typo3_content_planner';
    final public const EXT_NAME = 'XimaTypo3ContentPlanner';

    final public const STATUS_ICON_FLAG = 'flag';
    final public const STATUS_ICON_HEART = 'heart';
    final public const STATUS_ICON_TAG = 'tag';
    final public const STATUS_ICON_STAR = 'star';
    final public const STATUS_ICON_INFO = 'info';

    final public const STATUS_ICONS = [
        self::STATUS_ICON_FLAG,
        self::STATUS_ICON_HEART,
        self::STATUS_ICON_TAG,
        self::STATUS_ICON_STAR,
        self::STATUS_ICON_INFO,

    ];

    final public const STATUS_COLOR_BLACK = 'black';
    final public const STATUS_COLOR_BLUE = 'blue';
    final public const STATUS_COLOR_GREEN = 'green';
    final public const STATUS_COLOR_YELLOW = 'yellow';
    final public const STATUS_COLOR_RED = 'red';
    final public const STATUS_COLOR_GRAY = 'gray';

    final public const STATUS_COLORS = [
        self::STATUS_COLOR_BLACK,
        self::STATUS_COLOR_BLUE,
        self::STATUS_COLOR_GREEN,
        self::STATUS_COLOR_YELLOW,
        self::STATUS_COLOR_RED,
        self::STATUS_COLOR_GRAY,
    ];

    final public const STATUS_COLOR_CODES = [
        self::STATUS_COLOR_RED => '#f8d7da',
        self::STATUS_COLOR_BLUE => '#cce5ff',
        self::STATUS_COLOR_YELLOW => '#fff3cd',
        self::STATUS_COLOR_GREEN => '#d4edda',
    ];

    final public const STATUS_COLOR_ALERTS = [
        self::STATUS_COLOR_RED => 'danger',
        self::STATUS_COLOR_BLUE => 'info',
        self::STATUS_COLOR_YELLOW => 'warning',
        self::STATUS_COLOR_GREEN => 'success',
    ];

    final public const FEATURE_AUTO_ASSIGN = 'autoAssignment';

    final public const CACHE_IDENTIFIER = 'ximatypo3contentplanner';
}
