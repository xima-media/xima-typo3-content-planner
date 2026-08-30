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

/**
 * ImmediateEmailQueueRepository.
 *
 * Persistence for `tx_ximatypo3contentplanner_immediate_queue`, the throttle/batch store behind
 * {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\Immediate\ImmediateEmailService} (issue
 * #306). Deliberately its own table rather than a reuse of `tx_ximatypo3contentplanner_notification`
 * (the digest's store, issue #302): a recipient's immediate-mode queue must stay independent of
 * whatever {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\Channel\DatabaseChannel} does
 * with that other table, so the two channels never interfere with each other's bookkeeping.
 *
 * Writes go through raw QueryBuilder operations, following the same convention as
 * {@see NotificationRepository} - this table is never edited via FormEngine/DataHandler.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class ImmediateEmailQueueRepository
{
    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /**
     * @throws Exception
     */
    public function enqueue(Notification $notification): void
    {
        $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_IMMEDIATE_QUEUE)
            ->insert(Configuration::TABLE_IMMEDIATE_QUEUE)
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
     * The most recent time a batch was actually sent for this `(backend_user, tablename,
     * record_uid)` triple, `null` when none ever was - the throttle window's anchor.
     *
     * @throws Exception
     */
    public function findLastSentAt(int $backendUserUid, string $table, int $recordUid): ?int
    {
        $queryBuilder = $this->queryBuilderScopedTo($backendUserUid, $table, $recordUid);

        $lastSentAt = $queryBuilder
            ->selectLiteral('MAX('.$queryBuilder->quoteIdentifier('sent_at').') AS last_sent_at')
            ->executeQuery()
            ->fetchOne();

        return null !== $lastSentAt ? (int) $lastSentAt : null;
    }

    /**
     * Not-yet-sent rows for one `(backend_user, tablename, record_uid)` triple, chronological -
     * exactly what {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\Digest\DigestGroupBuilder::build()}
     * needs to collapse into the single group a batched immediate mail renders.
     *
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    public function findPending(int $backendUserUid, string $table, int $recordUid): array
    {
        $queryBuilder = $this->queryBuilderScopedTo($backendUserUid, $table, $recordUid);
        $queryBuilder->andWhere($queryBuilder->expr()->isNull('sent_at'));

        return $queryBuilder
            ->select('*')
            ->orderBy('crdate', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Marks exactly the given queue uids sent, in a single atomic `UPDATE` - same ownership/race
     * safety rationale as {@see NotificationRepository::markDigestedByUids()}.
     *
     * @param list<int> $uids
     *
     * @throws Exception
     */
    /**
     * Claims the given queue rows by stamping sent_at, and reports how many were actually
     * claimed. The "sent_at IS NULL" guard makes this the single point of arbitration: two
     * concurrent saves for the same record both see a pending queue, but only one of them
     * gets a non-zero count back and is allowed to send.
     *
     * @param list<int> $uids
     *
     * @throws Exception
     */
    public function markSentByUids(array $uids, int $sentAt): int
    {
        if ([] === $uids) {
            return 0;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_IMMEDIATE_QUEUE);

        return (int) $queryBuilder
            ->update(Configuration::TABLE_IMMEDIATE_QUEUE)
            ->set('sent_at', $sentAt)
            ->where(
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->isNull('sent_at'),
            )
            ->executeStatement();
    }

    /**
     * Removes queue rows that were already sent before $threshold. They are transport
     * bookkeeping only - the notification itself lives in the notification table and has its
     * own retention - so without this the queue is the one table in the feature that grows
     * without bound.
     *
     * @throws Exception
     */
    public function deleteSentOlderThan(int $threshold, bool $dryRun): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_IMMEDIATE_QUEUE);
        $constraints = [
            $queryBuilder->expr()->isNotNull('sent_at'),
            $queryBuilder->expr()->lt('sent_at', $queryBuilder->createNamedParameter($threshold, Connection::PARAM_INT)),
        ];

        if ($dryRun) {
            return (int) $queryBuilder
                ->count('uid')
                ->from(Configuration::TABLE_IMMEDIATE_QUEUE)
                ->where(...$constraints)
                ->executeQuery()
                ->fetchOne();
        }

        return (int) $queryBuilder
            ->delete(Configuration::TABLE_IMMEDIATE_QUEUE)
            ->where(...$constraints)
            ->executeStatement();
    }

    /**
     * How many mails this recipient has already been sent since $since, across all records.
     * The per-record throttle alone does not bound the total: changing many different watched
     * records in quick succession produces one mail each.
     *
     * @throws Exception
     */
    public function countSentSince(int $backendUserUid, int $since): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_IMMEDIATE_QUEUE);

        return (int) $queryBuilder
            ->count('uid')
            ->from(Configuration::TABLE_IMMEDIATE_QUEUE)
            ->where(
                $queryBuilder->expr()->eq('backend_user', $queryBuilder->createNamedParameter($backendUserUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->gte('sent_at', $queryBuilder->createNamedParameter($since, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchOne();
    }

    private function queryBuilderScopedTo(int $backendUserUid, string $table, int $recordUid): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_IMMEDIATE_QUEUE);

        return $queryBuilder
            ->from(Configuration::TABLE_IMMEDIATE_QUEUE)
            ->where(
                $queryBuilder->expr()->eq('backend_user', $queryBuilder->createNamedParameter($backendUserUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('tablename', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->eq('record_uid', $queryBuilder->createNamedParameter($recordUid, Connection::PARAM_INT)),
            );
    }
}
