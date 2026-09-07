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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\EventListener\Notification;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Domain\Model\NotificationEventType;
use Xima\XimaTypo3ContentPlanner\Event\AssigneeChangedEvent;
use Xima\XimaTypo3ContentPlanner\EventListener\Notification\NotifyOnAssigneeChangedListener;
use Xima\XimaTypo3ContentPlanner\Service\Notification\{NotificationDispatcher, NotificationPayloadFactory};

/**
 * NotifyOnAssigneeChangedListenerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class NotifyOnAssigneeChangedListenerTest extends TestCase
{
    #[Test]
    public function dispatchesWithThePayloadFromTheFactoryWhenNewlyAssigned(): void
    {
        $event = new AssigneeChangedEvent('pages', 5, null, 2, 1);
        $payload = ['version' => 1, 'title' => 'Home'];

        $payloadFactory = $this->createMock(NotificationPayloadFactory::class);
        $payloadFactory->method('forAssigneeChanged')->with($event)->willReturn($payload);

        $dispatcher = $this->createMock(NotificationDispatcher::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(NotificationEventType::Assigned, 'pages', 5, 1, $payload);

        (new NotifyOnAssigneeChangedListener($dispatcher, $payloadFactory))($event);
    }

    #[Test]
    public function doesNothingWhenUnassigned(): void
    {
        $event = new AssigneeChangedEvent('pages', 5, 2, null, 1);

        $dispatcher = $this->createMock(NotificationDispatcher::class);
        $dispatcher->expects(self::never())->method('dispatch');

        (new NotifyOnAssigneeChangedListener($dispatcher, $this->createMock(NotificationPayloadFactory::class)))($event);
    }
}
