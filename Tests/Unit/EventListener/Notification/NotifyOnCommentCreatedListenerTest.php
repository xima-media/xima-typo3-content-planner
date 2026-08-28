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
use Xima\XimaTypo3ContentPlanner\Event\CommentCreatedEvent;
use Xima\XimaTypo3ContentPlanner\EventListener\Notification\NotifyOnCommentCreatedListener;
use Xima\XimaTypo3ContentPlanner\Service\Notification\{NotificationDispatcher, NotificationPayloadFactory};

/**
 * NotifyOnCommentCreatedListenerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class NotifyOnCommentCreatedListenerTest extends TestCase
{
    #[Test]
    public function dispatchesWithThePayloadFromTheFactoryAndTheAuthorAsActor(): void
    {
        $event = new CommentCreatedEvent('pages', 5, 42, 1);
        $payload = ['version' => 1, 'title' => 'Home', 'commentExcerpt' => 'Hello'];

        $payloadFactory = $this->createMock(NotificationPayloadFactory::class);
        $payloadFactory->method('forCommentCreated')->with($event)->willReturn($payload);

        $dispatcher = $this->createMock(NotificationDispatcher::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(NotificationEventType::CommentAdded, 'pages', 5, 1, $payload);

        (new NotifyOnCommentCreatedListener($dispatcher, $payloadFactory))($event);
    }
}
