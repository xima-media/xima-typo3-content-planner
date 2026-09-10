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

namespace Xima\XimaTypo3ContentPlanner\Domain\Model\Dto;

/**
 * RetentionRunResult.
 *
 * Outcome of one
 * {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\Retention\NotificationRetentionService::run()}
 * call (issue #304) - what `content-planner:notification:cleanup` reports, both for its
 * `--dry-run` summary (counts of what *would* be deleted) and its real run (counts of what
 * actually was).
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class RetentionRunResult
{
    public function __construct(
        private int $readNotificationsDeleted,
        private int $unreadNotificationsDeleted,
        private int $orphanedNotificationsDeleted,
        private int $orphanedWatchersDeleted,
        private bool $dryRun,
    ) {}

    public function getReadNotificationsDeleted(): int
    {
        return $this->readNotificationsDeleted;
    }

    public function getUnreadNotificationsDeleted(): int
    {
        return $this->unreadNotificationsDeleted;
    }

    public function getOrphanedNotificationsDeleted(): int
    {
        return $this->orphanedNotificationsDeleted;
    }

    public function getOrphanedWatchersDeleted(): int
    {
        return $this->orphanedWatchersDeleted;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    public function getTotalDeleted(): int
    {
        return $this->readNotificationsDeleted
            + $this->unreadNotificationsDeleted
            + $this->orphanedNotificationsDeleted
            + $this->orphanedWatchersDeleted;
    }
}
