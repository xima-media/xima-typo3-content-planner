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
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Notification;

use function count;
use function intval;

/**
 * NotificationRepository.
 *
 * Persistence for `tx_ximatypo3contentplanner_notification`. Writes go through raw QueryBuilder
 * operations (this table is never edited via FormEngine/DataHandler), following the same
 * convention as {@see WatcherRepository}.
 *
 * Read access ({@see self::findLatestByRecipient()}, {@see self::countUnreadByRecipient()}) and the
 * mark-as-read mutations back the backend toolbar notification center (issue #301). The
 * age-based and orphan cleanup methods back
 * {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\Retention\NotificationRetentionService}
 * (issue #304).
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class NotificationRepository
{
    /**
     * Upper bound on how many rows a single DELETE (or its preceding candidate-uid SELECT)
     * touches (issue #304's "safe on large tables" requirement). Chosen to keep each statement
     * fast and short-lived rather than for any particular platform limit.
     */
    private const DELETE_CHUNK_SIZE = 500;

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
     * Distinct recipients with at least one non-digested notification (issue #302's email
     * digest). Deliberately includes already-read rows: reading a notification in the backend
     * toolbar must not silently drop it from the digest, per the issue's acceptance criteria.
     *
     * @return list<int>
     *
     * @throws Exception
     */
    public function findRecipientsWithPendingDigest(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_NOTIFICATION);

        $rows = $queryBuilder
            ->select('backend_user')
            ->distinct()
            ->from(Configuration::TABLE_NOTIFICATION)
            ->where($queryBuilder->expr()->isNull('digested_at'))
            ->executeQuery()
            ->fetchFirstColumn();

        return array_map(intval(...), $rows);
    }

    /**
     * All non-digested notifications for one recipient, ordered so that every notification for
     * the same record is adjacent and chronological within it - exactly what
     * {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\Digest\DigestGroupBuilder} needs
     * to group and dedupe per record.
     *
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    public function findPendingByRecipient(int $backendUserUid): array
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
                $queryBuilder->expr()->isNull('digested_at'),
            )
            ->orderBy('tablename')
            ->addOrderBy('record_uid')
            ->addOrderBy('crdate', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Marks exactly the given notification uids as digested for one recipient, in a single
     * atomic `UPDATE`. Scoped to `backend_user` (same ownership guard as {@see self::markAsRead()})
     * and to `digested_at IS NULL`, so re-running the digest command concurrently can never
     * double-mark a row or mark one the caller never actually rendered into a mail - the mail is
     * always sent *before* this call, so a crash between the two simply leaves the notification
     * pending for the next run rather than silently losing it.
     *
     * @param list<int> $uids
     *
     * @throws Exception
     */
    public function markDigestedByUids(array $uids, int $backendUserUid): int
    {
        if ([] === $uids) {
            return 0;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_NOTIFICATION);

        return $queryBuilder
            ->update(Configuration::TABLE_NOTIFICATION)
            ->set('digested_at', time())
            ->where(
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->eq(
                    'backend_user',
                    $queryBuilder->createNamedParameter($backendUserUid, Connection::PARAM_INT),
                ),
                $queryBuilder->expr()->isNull('digested_at'),
            )
            ->executeStatement();
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

    /**
     * Deletes (or, with `$dryRun`, only counts) notifications in the given read state created
     * before `$timestamp` - the read/unread retention rules from issue #304's
     * `content-planner:notification:cleanup` command.
     *
     * @throws Exception
     */
    public function deleteOlderThan(bool $read, int $timestamp, bool $dryRun): int
    {
        return $this->deleteMatchingInChunks(static function (QueryBuilder $queryBuilder) use ($read, $timestamp): void {
            $queryBuilder->andWhere($read ? $queryBuilder->expr()->isNotNull('read_at') : $queryBuilder->expr()->isNull('read_at'));
            $queryBuilder->andWhere($queryBuilder->expr()->lt('crdate', $queryBuilder->createNamedParameter($timestamp, Connection::PARAM_INT)));
        }, $dryRun);
    }

    /**
     * Distinct `(tablename, record_uid)` pairs referenced by any notification, for the orphaned-record
     * cleanup rule (issue #304): the caller checks which of these still have a live record and
     * deletes the rest via {@see self::deleteForTableAndRecordUids()}.
     *
     * @return list<array{tablename: string, record_uid: int}>
     *
     * @throws Exception
     */
    public function findDistinctTableRecordPairs(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_NOTIFICATION);

        $rows = $queryBuilder
            ->select('tablename', 'record_uid')
            ->distinct()
            ->from(Configuration::TABLE_NOTIFICATION)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(
            static fn (array $row): array => ['tablename' => (string) $row['tablename'], 'record_uid' => (int) $row['record_uid']],
            $rows,
        );
    }

    /**
     * Distinct recipient uids referenced by any notification, for the orphaned-backend-user
     * cleanup rule (issue #304).
     *
     * @return list<int>
     *
     * @throws Exception
     */
    public function findDistinctBackendUsers(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_NOTIFICATION);

        $rows = $queryBuilder
            ->select('backend_user')
            ->distinct()
            ->from(Configuration::TABLE_NOTIFICATION)
            ->executeQuery()
            ->fetchFirstColumn();

        return array_map(intval(...), $rows);
    }

    /**
     * Deletes (or, with `$dryRun`, only counts) every notification for the given orphaned record.
     *
     * @param list<int> $recordUids
     *
     * @throws Exception
     */
    public function deleteForTableAndRecordUids(string $table, array $recordUids, bool $dryRun): int
    {
        if ([] === $recordUids) {
            return 0;
        }

        return $this->deleteMatchingInChunks(static function (QueryBuilder $queryBuilder) use ($table, $recordUids): void {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('tablename', $queryBuilder->createNamedParameter($table)));
            $queryBuilder->andWhere($queryBuilder->expr()->in('record_uid', $queryBuilder->createNamedParameter($recordUids, Connection::PARAM_INT_ARRAY)));
        }, $dryRun);
    }

    /**
     * Deletes (or, with `$dryRun`, only counts) every notification recipient-owned by one of the
     * given (deleted/disabled) backend users.
     *
     * @param list<int> $backendUserUids
     *
     * @throws Exception
     */
    public function deleteForBackendUsers(array $backendUserUids, bool $dryRun): int
    {
        if ([] === $backendUserUids) {
            return 0;
        }

        return $this->deleteMatchingInChunks(static function (QueryBuilder $queryBuilder) use ($backendUserUids): void {
            $queryBuilder->andWhere($queryBuilder->expr()->in('backend_user', $queryBuilder->createNamedParameter($backendUserUids, Connection::PARAM_INT_ARRAY)));
        }, $dryRun);
    }

    /**
     * Shared engine for every retention/orphan rule above: `$configureWhere` narrows a
     * `SELECT uid` query builder to the rows a rule targets, and this method either counts them
     * (`$dryRun`) or repeatedly selects up to {@see self::DELETE_CHUNK_SIZE} matching uids and
     * deletes exactly those, until nothing more matches. Bounding both the SELECT and the DELETE
     * to a fixed-size batch keeps either statement's cost independent of total table size - safe
     * to run against a notification table with millions of historic rows, unlike one unbounded
     * `DELETE ... WHERE ...` holding a single long-running lock.
     *
     * @throws Exception
     */
    private function deleteMatchingInChunks(callable $configureWhere, bool $dryRun): int
    {
        if ($dryRun) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_NOTIFICATION);
            $queryBuilder->count('uid')->from(Configuration::TABLE_NOTIFICATION);
            $configureWhere($queryBuilder);

            return (int) $queryBuilder->executeQuery()->fetchOne();
        }

        $deleted = 0;
        do {
            $selectQueryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_NOTIFICATION);
            $selectQueryBuilder->select('uid')->from(Configuration::TABLE_NOTIFICATION);
            $configureWhere($selectQueryBuilder);
            $uids = array_map(intval(...), $selectQueryBuilder
                ->orderBy('uid')
                ->setMaxResults(self::DELETE_CHUNK_SIZE)
                ->executeQuery()
                ->fetchFirstColumn());

            if ([] === $uids) {
                break;
            }

            $deleteQueryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_NOTIFICATION);
            $deleteQueryBuilder
                ->delete(Configuration::TABLE_NOTIFICATION)
                ->where($deleteQueryBuilder->expr()->in('uid', $deleteQueryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)));

            $deleted += $deleteQueryBuilder->executeStatement();
        } while (self::DELETE_CHUNK_SIZE === count($uids));

        return $deleted;
    }
}
