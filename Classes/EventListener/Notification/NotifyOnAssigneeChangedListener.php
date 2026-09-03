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
use Xima\XimaTypo3ContentPlanner\Event\AssigneeChangedEvent;
use Xima\XimaTypo3ContentPlanner\Service\Notification\{NotificationDispatcher, NotificationPayloadFactory};

/**
 * NotifyOnAssigneeChangedListener.
 *
 * Registered to run after {@see \Xima\XimaTypo3ContentPlanner\EventListener\Watcher\AutoWatchOnAssigneeChangedListener}
 * so that a newly assigned user (auto-watching this record for the first time as a side effect of
 * this very event) is already resolvable as a recipient by {@see NotificationDispatcher}.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
#[AsEventListener(
    identifier: 'xima-typo3-content-planner/notification/notify-on-assignee-changed',
    after: 'xima-typo3-content-planner/watcher/auto-watch-on-assignee-changed',
)]
final readonly class NotifyOnAssigneeChangedListener
{
    public function __construct(
        private NotificationDispatcher $notificationDispatcher,
        private NotificationPayloadFactory $payloadFactory,
    ) {}

    /**
     * @throws Exception
     */
    public function __invoke(AssigneeChangedEvent $event): void
    {
        if (null === $event->getNewAssignee()) {
            return;
        }

        $this->notificationDispatcher->dispatch(
            NotificationEventType::Assigned,
            $event->getTable(),
            $event->getUid(),
            $event->getActorUid(),
            $this->payloadFactory->forAssigneeChanged($event),
        );
    }
}
