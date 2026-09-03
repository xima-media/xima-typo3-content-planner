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

namespace Xima\XimaTypo3ContentPlanner\Domain\Repository;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Notification;

/**
 * NotificationRepository.
 *
 * Persistence for `tx_ximatypo3contentplanner_notification`. Writes go through raw QueryBuilder
 * operations (this table is never edited via FormEngine/DataHandler), following the same
 * convention as {@see WatcherRepository}.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class NotificationRepository
{
    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /**
     * @throws Exception
     */
    public function create(Notification $notification): void
    {
        $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_NOTIFICATION)
            ->insert(Configuration::TABLE_NOTIFICATION)
            ->values([
                'pid' => 0,
                'backend_user' => $notification->getRecipientUid(),
                'event_type' => $notification->getEventType()->value,
                'tablename' => $notification->getTable(),
                'record_uid' => $notification->getRecordUid(),
                'actor' => $notification->getActorUid(),
                'reason' => $notification->getReason()->value,
                'payload' => json_encode($notification->getPayload(), \JSON_THROW_ON_ERROR),
                'crdate' => $notification->getCrdate(),
            ])
            ->executeStatement();
    }
}
