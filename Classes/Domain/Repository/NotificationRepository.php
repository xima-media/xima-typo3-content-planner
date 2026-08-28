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
use TYPO3\CMS\Core\Database\{Connection, ConnectionPool};
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Notification;

/**
 * NotificationRepository.
 *
 * Persistence for `tx_ximatypo3contentplanner_notification`. Writes go through raw QueryBuilder
 * operations (this table is never edited via FormEngine/DataHandler), following the same
 * convention as {@see WatcherRepository}.
 *
 * Read access ({@see self::findLatestByRecipient()}, {@see self::countUnreadByRecipient()}) and the
 * mark-as-read mutations back the backend toolbar notification center (issue #301).
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

    /**
     * Latest notifications for the toolbar dropdown, unread first (then most recent first within
     * each group). Deliberately returns raw rows rather than a hydrated {@see Notification}: that
     * model has no uid/read_at, both of which the toolbar needs.
     *
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    public function findLatestByRecipient(int $backendUserUid, int $limit): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_NOTIFICATION);

        return $queryBuilder
            ->select('*')
            ->from(Configuration::TABLE_NOTIFICATION)
            ->where(
                $queryBuilder->expr()->eq(
                    'backend_user',
                    $queryBuilder->createNamedParameter($backendUserUid, Connection::PARAM_INT),
                ),
            )
            // read_at IS NULL sorts first in both MySQL/MariaDB and SQLite ascending NULL ordering.
            ->orderBy('read_at', 'ASC')
            ->addOrderBy('crdate', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @throws Exception
     */
    public function countUnreadByRecipient(int $backendUserUid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_NOTIFICATION);

        return (int) $queryBuilder
            ->count('uid')
            ->from(Configuration::TABLE_NOTIFICATION)
            ->where(
                $queryBuilder->expr()->eq(
                    'backend_user',
                    $queryBuilder->createNamedParameter($backendUserUid, Connection::PARAM_INT),
                ),
                $queryBuilder->expr()->isNull('read_at'),
            )
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Marks a single notification read, scoped to its recipient so one user can never mark
     * another user's notification read via a guessed uid.
     *
     * @throws Exception
     */
    public function markAsRead(int $uid, int $backendUserUid): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_NOTIFICATION);

        $affected = $queryBuilder
            ->update(Configuration::TABLE_NOTIFICATION)
            ->set('read_at', time())
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq(
                    'backend_user',
                    $queryBuilder->createNamedParameter($backendUserUid, Connection::PARAM_INT),
                ),
                $queryBuilder->expr()->isNull('read_at'),
            )
            ->executeStatement();

        return $affected > 0;
    }

    /**
     * @throws Exception
     */
    public function markAllAsRead(int $backendUserUid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_NOTIFICATION);

        return $queryBuilder
            ->update(Configuration::TABLE_NOTIFICATION)
            ->set('read_at', time())
            ->where(
                $queryBuilder->expr()->eq(
                    'backend_user',
                    $queryBuilder->createNamedParameter($backendUserUid, Connection::PARAM_INT),
                ),
                $queryBuilder->expr()->isNull('read_at'),
            )
            ->executeStatement();
    }
}
