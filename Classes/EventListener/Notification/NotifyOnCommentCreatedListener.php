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

namespace Xima\XimaTypo3ContentPlanner\EventListener\Notification;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use Xima\XimaTypo3ContentPlanner\Domain\Model\NotificationEventType;
use Xima\XimaTypo3ContentPlanner\Event\CommentCreatedEvent;
use Xima\XimaTypo3ContentPlanner\Service\Notification\{NotificationDispatcher, NotificationPayloadFactory};

/**
 * NotifyOnCommentCreatedListener.
 *
 * Registered to run after {@see \Xima\XimaTypo3ContentPlanner\EventListener\Watcher\AutoWatchOnCommentCreatedListener}
 * so that the comment's author (auto-watching this record for the first time as a side effect of
 * this very comment) is already resolvable as a recipient by {@see NotificationDispatcher}. The
 * author is excluded from the recipients regardless, via the `actorUid` argument below.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
#[AsEventListener(
    identifier: 'xima-typo3-content-planner/notification/notify-on-comment-created',
    after: 'xima-typo3-content-planner/watcher/auto-watch-on-comment-created',
)]
final readonly class NotifyOnCommentCreatedListener
{
    public function __construct(
        private NotificationDispatcher $notificationDispatcher,
        private NotificationPayloadFactory $payloadFactory,
    ) {}

    /**
     * @throws Exception
     */
    public function __invoke(CommentCreatedEvent $event): void
    {
        $this->notificationDispatcher->dispatch(
            NotificationEventType::CommentAdded,
            $event->getTable(),
            $event->getRecordUid(),
            $event->getAuthorUid(),
            $this->payloadFactory->forCommentCreated($event),
        );
    }
}
