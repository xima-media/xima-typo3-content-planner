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
 * NotificationReason.
 *
 * Machine-readable "why you receive this" code, persisted in the `reason` column of
 * `tx_ximatypo3contentplanner_notification`. Derived purely from the recipient's
 * {@see WatchSource} at dispatch time (see {@see self::fromWatchSource()}) - the triggering
 * {@see NotificationEventType} does not further refine it, since a recipient's reason for
 * watching a record does not change per event.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
enum NotificationReason: string
{
    case WatchingSinceAssignment = 'watching_since_assignment';
    case WatchingSinceComment = 'watching_since_comment';
    case WatchingSinceStatusChange = 'watching_since_status_change';
    case WatchingManually = 'watching_manually';
    case Mentioned = 'mentioned';

    public static function fromWatchSource(WatchSource $source): self
    {
        return match ($source) {
            WatchSource::Assignment => self::WatchingSinceAssignment,
            WatchSource::Comment => self::WatchingSinceComment,
            WatchSource::StatusChange => self::WatchingSinceStatusChange,
            WatchSource::Manual => self::WatchingManually,
            WatchSource::Mention => self::Mentioned,
        };
    }
}
