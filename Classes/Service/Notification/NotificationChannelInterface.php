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

namespace Xima\XimaTypo3ContentPlanner\Service\Notification;

use Xima\XimaTypo3ContentPlanner\Domain\Model\Notification;

/**
 * NotificationChannelInterface.
 *
 * A channel decides for itself whether it wants to handle a given {@see Notification}
 * ({@see self::supports()}, typically gated behind its own extension configuration toggle) and,
 * if so, delivers it ({@see self::deliver()}).
 *
 * Third-party extensions register additional channels by tagging their service with
 * `xima_typo3_content_planner.notification_channel` in `Configuration/Services.yaml` (the same
 * pattern as the `dashboard.widget` tag used for dashboard widgets):
 *
 * ```yaml
 * MyVendor\MyExtension\Notification\SlackChannel:
 *   tags:
 *     - name: xima_typo3_content_planner.notification_channel
 * ```
 *
 * See Documentation/DeveloperCorner/Notifications.rst for the full extensibility guide.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
interface NotificationChannelInterface
{
    public function supports(Notification $notification): bool;

    public function deliver(Notification $notification): void;
}
