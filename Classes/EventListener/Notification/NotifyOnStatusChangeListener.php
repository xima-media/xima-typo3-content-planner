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
use Xima\XimaTypo3ContentPlanner\Event\StatusChangeEvent;
use Xima\XimaTypo3ContentPlanner\Service\Notification\{NotificationDispatcher, NotificationPayloadFactory};

/**
 * NotifyOnStatusChangeListener.
 *
 * Registered to run after {@see \Xima\XimaTypo3ContentPlanner\EventListener\Watcher\AutoWatchOnStatusChangeListener}
 * so that an actor who becomes a watcher of a record as a side effect of this very status change
 * is already excludable/includable correctly by the time {@see NotificationDispatcher} resolves
 * watchers.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
#[AsEventListener(
    identifier: 'xima-typo3-content-planner/notification/notify-on-status-change',
    after: 'xima-typo3-content-planner/watcher/auto-watch-on-status-change',
)]
final readonly class NotifyOnStatusChangeListener
{
    public function __construct(
        private NotificationDispatcher $notificationDispatcher,
        private NotificationPayloadFactory $payloadFactory,
    ) {}

    /**
     * @throws Exception
     */
    public function __invoke(StatusChangeEvent $event): void
    {
        $this->notificationDispatcher->dispatch(
            NotificationEventType::StatusChanged,
            $event->getTable(),
            $event->getUid(),
            $event->getActorUid(),
            $this->payloadFactory->forStatusChange($event),
        );
    }
}
