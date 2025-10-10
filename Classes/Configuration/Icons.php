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
