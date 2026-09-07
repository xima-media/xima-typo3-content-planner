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
use Xima\XimaTypo3ContentPlanner\Event\CommentCreatedEvent;
use Xima\XimaTypo3ContentPlanner\Service\WatcherService;

/**
 * AutoWatchOnCommentCreatedListener.
 *
 * Auto-watch trigger: commenting on a record makes the comment's author a watcher of that
 * record. Applies to both root comments and replies.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
#[AsEventListener(identifier: 'xima-typo3-content-planner/watcher/auto-watch-on-comment-created')]
final readonly class AutoWatchOnCommentCreatedListener
{
    public function __construct(private WatcherService $watcherService) {}

    /**
     * @throws Exception
     */
    public function __invoke(CommentCreatedEvent $event): void
    {
        if (!$this->watcherService->isWatchable($event->getTable())) {
            return;
        }

        $this->watcherService->watch($event->getTable(), $event->getRecordUid(), $event->getAuthorUid(), WatchSource::Comment);
    }
}
