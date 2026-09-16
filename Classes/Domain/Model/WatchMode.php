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
 * WatchMode.
 *
 * Persisted in the `mode` column of `tx_ximatypo3contentplanner_watcher`.
 *
 * - {@see self::Auto}: created as a side effect of an auto-watch trigger (assignment, comment,
 *   status change). Never overrides an existing manual decision.
 * - {@see self::ManualWatch}: the user explicitly chose to watch. Sticky against auto triggers.
 * - {@see self::ManualUnwatch}: the user explicitly chose to stop watching. Sticky against auto
 *   triggers - only an explicit manual watch reactivates it.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
enum WatchMode: string
{
    case Auto = 'auto';
    case ManualWatch = 'manual_watch';
    case ManualUnwatch = 'manual_unwatch';

    /**
     * Whether a (possibly absent) mode counts as "actively watching" - i.e. every mode except
     * {@see self::ManualUnwatch}, and not `null` (no watcher row at all). Single source of truth
     * for this predicate, shared by {@see \Xima\XimaTypo3ContentPlanner\Service\WatcherService::isWatching()}
     * and {@see \Xima\XimaTypo3ContentPlanner\Service\WatcherPresentationService} (issue #303's
     * watch/unwatch UI), which both need it without an extra database round trip.
     */
    public static function isWatching(?self $mode): bool
    {
        return null !== $mode && self::ManualUnwatch !== $mode;
    }
}
