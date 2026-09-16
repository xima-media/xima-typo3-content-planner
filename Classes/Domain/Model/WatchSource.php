<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Domain\Model;

/**
 * WatchSource.
 *
 * Persisted in the `source` column of `tx_ximatypo3contentplanner_watcher`. Records *why* a
 * watcher relation exists, independent of its current {@see WatchMode}.
 *
 * {@see self::Mention} is reserved for a future auto-watch trigger and is not dispatched by
 * this issue's scope.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
enum WatchSource: string
{
    case Assignment = 'assignment';
    case Comment = 'comment';
    case StatusChange = 'status_change';
    case Manual = 'manual';
    case Mention = 'mention';
}
