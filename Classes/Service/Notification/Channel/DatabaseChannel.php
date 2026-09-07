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

namespace Xima\XimaTypo3ContentPlanner\Service\Notification\Channel;

use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Notification;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\NotificationRepository;
use Xima\XimaTypo3ContentPlanner\Service\Notification\NotificationChannelInterface;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

/**
 * DatabaseChannel.
 *
 * MVP notification channel: persists the notification record. Consumed by the backend badge
 * (#301) and the email digest (#302), neither of which is this class's concern.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class DatabaseChannel implements NotificationChannelInterface
{
    public function __construct(private NotificationRepository $notificationRepository) {}

    public function supports(Notification $notification): bool
    {
        return ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_NOTIFICATION_CHANNEL_DATABASE);
    }

    public function deliver(Notification $notification): void
    {
        $this->notificationRepository->create($notification);
    }
}
