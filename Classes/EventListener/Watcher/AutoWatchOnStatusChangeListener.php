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

namespace Xima\XimaTypo3ContentPlanner\EventListener\Watcher;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use Xima\XimaTypo3ContentPlanner\Domain\Model\WatchSource;
use Xima\XimaTypo3ContentPlanner\Event\StatusChangeEvent;
use Xima\XimaTypo3ContentPlanner\Service\WatcherService;

/**
 * AutoWatchOnStatusChangeListener.
 *
 * Auto-watch trigger: a user who changes a record's status becomes a watcher of that record. No
 * watcher is created when the actor is unknown (e.g. CLI context without an authenticated
 * backend user) - see {@see StatusChangeEvent::getActorUid()}.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
#[AsEventListener(identifier: 'xima-typo3-content-planner/watcher/auto-watch-on-status-change')]
final readonly class AutoWatchOnStatusChangeListener
{
    public function __construct(private WatcherService $watcherService) {}

    /**
     * @throws Exception
     */
    public function __invoke(StatusChangeEvent $event): void
    {
        $actorUid = $event->getActorUid();
        if (null === $actorUid || !$this->watcherService->isWatchable($event->getTable())) {
            return;
        }

        $this->watcherService->watch($event->getTable(), $event->getUid(), $actorUid, WatchSource::StatusChange);
    }
}
