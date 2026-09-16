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
 * additional cases as further triggers are wired.
 *
 * {@see self::ContentChanged} (issue #309) is dispatched directly from
 * {@see \Xima\XimaTypo3ContentPlanner\Hooks\DataHandlerHook} rather than from a PSR-14 event,
 * and is the only event type whose rows are aggregated in place (see
 * {@see \Xima\XimaTypo3ContentPlanner\Domain\Repository\NotificationRepository::upsertContentChange()})
 * instead of one row per dispatch.
 *
 * {@see self::Mentioned} (issue #305) is dispatched via
 * {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\NotificationDispatcher::dispatchMention()},
 * which bypasses the "must be an active watcher" gate every other event type goes through - see
 * that method's docblock and Documentation/DeveloperCorner/Notifications.rst's "Mentions"
 * section for the full mute-vs-mention reasoning.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
enum NotificationEventType: string
{
    case StatusChanged = 'status_changed';
    case Assigned = 'assigned';
    case CommentAdded = 'comment_added';
    case ContentChanged = 'content_changed';
    case Mentioned = 'mentioned';
}
