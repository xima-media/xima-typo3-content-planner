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
use Xima\XimaTypo3ContentPlanner\Event\StatusChangeEvent;
use Xima\XimaTypo3ContentPlanner\EventListener\Notification\NotifyOnStatusChangeListener;
use Xima\XimaTypo3ContentPlanner\Service\Notification\{NotificationDispatcher, NotificationPayloadFactory};

/**
 * NotifyOnStatusChangeListenerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class NotifyOnStatusChangeListenerTest extends TestCase
{
    #[Test]
    public function dispatchesWithThePayloadFromTheFactory(): void
    {
        $event = new StatusChangeEvent('pages', 5, [], null, null, 1);
        $payload = ['version' => 1, 'title' => 'Home'];

        $payloadFactory = $this->createMock(NotificationPayloadFactory::class);
        $payloadFactory->method('forStatusChange')->with($event)->willReturn($payload);

        $dispatcher = $this->createMock(NotificationDispatcher::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(NotificationEventType::StatusChanged, 'pages', 5, 1, $payload);

        (new NotifyOnStatusChangeListener($dispatcher, $payloadFactory))($event);
    }
}
