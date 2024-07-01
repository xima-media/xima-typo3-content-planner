<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner;

class Configuration
{
    final public const EXT_KEY = 'xima_typo3_content_planner';
    final public const EXT_NAME = 'XimaTypo3ContentPlanner';

    final public const STATUS_DANGER = 'danger';
    final public const STATUS_INFO = 'info';
    final public const STATUS_WARNING = 'warning';
    final public const STATUS_SUCCESS = 'success';

    final public const STATUS_ICONS = [
        self::STATUS_DANGER => 'flag-red',
        self::STATUS_INFO => 'flag-blue',
        self::STATUS_WARNING => 'flag-yellow',
        self::STATUS_SUCCESS => 'flag-green',
    ];

    final public const STATUS_COLORS = [
        self::STATUS_DANGER => '#f8d7da',
        self::STATUS_INFO => '#cce5ff',
        self::STATUS_WARNING => '#fff3cd',
        self::STATUS_SUCCESS => '#d4edda',
    ];
}
