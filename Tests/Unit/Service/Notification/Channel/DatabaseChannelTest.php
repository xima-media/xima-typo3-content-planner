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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Service\Notification\Channel;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Domain\Model\{Notification, NotificationEventType, NotificationReason};
use Xima\XimaTypo3ContentPlanner\Domain\Repository\NotificationRepository;
use Xima\XimaTypo3ContentPlanner\Service\Notification\Channel\DatabaseChannel;

/**
 * DatabaseChannelTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class DatabaseChannelTest extends TestCase
{
    #[Test]
    public function deliverCreatesTheNotificationViaTheRepository(): void
    {
        $notification = new Notification(2, NotificationEventType::StatusChanged, 'pages', 1, 1, NotificationReason::WatchingSinceAssignment, [], 1000);

        $notificationRepository = $this->createMock(NotificationRepository::class);
        $notificationRepository->expects(self::once())->method('create')->with($notification);

        (new DatabaseChannel($notificationRepository))->deliver($notification);
    }
}
