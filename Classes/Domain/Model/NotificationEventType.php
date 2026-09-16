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
 * NotificationEventType.
 *
 * Persisted in the `event_type` column of `tx_ximatypo3contentplanner_notification`. Identifies
 * which PSR-14 event triggered a notification. See issue #300 for the initial set; extend with
 * additional cases as further triggers are wired (e.g. `mentioned`, see issue #305).
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
enum NotificationEventType: string
{
    case StatusChanged = 'status_changed';
    case Assigned = 'assigned';
    case CommentAdded = 'comment_added';
}
