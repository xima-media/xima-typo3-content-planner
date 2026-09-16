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
     * Claims exactly the subset of $uids that is still unclaimed (not `sent_at IS NULL` alone -
     * `claimed_at IS NULL OR claimed_at < $leaseExpiry` also reclaims a stale claim whose sender
     * crashed/threw before reaching {@see self::markSent()}), and returns exactly those claimed
     * rows. Two concurrent callers sharing part of the same $uids snapshot each get back only the
     * subset their own `UPDATE` actually touched - the random per-call `claim_token` (not the
     * `claimed_at` timestamp, which two calls in the same second could share) is what makes the
     * follow-up `SELECT` race-free: only rows this exact call claimed carry this exact token.
     *
     * @param list<int> $uids
     *
     * @return list<array<string, mixed>> exactly the rows this call claimed, `payload` still a
     *                                    JSON string
     *
     * @throws Exception
     */
    public function claimPending(array $uids, int $leaseExpiry): array
    {
        if ([] === $uids) {
            return [];
        }

        $token = bin2hex(random_bytes(16));
        $now = time();

        $updateBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_IMMEDIATE_QUEUE);
        $affected = $updateBuilder
            ->update(Configuration::TABLE_IMMEDIATE_QUEUE)
            ->set('claimed_at', $now)
            ->set('claim_token', $token)
            ->where(
                $updateBuilder->expr()->in('uid', $updateBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)),
                $updateBuilder->expr()->isNull('sent_at'),
                $updateBuilder->expr()->or(
                    $updateBuilder->expr()->isNull('claimed_at'),
                    $updateBuilder->expr()->lt('claimed_at', $updateBuilder->createNamedParameter($leaseExpiry, Connection::PARAM_INT)),
                ),
            )
            ->executeStatement();

        if (0 === $affected) {
            return [];
        }

        $selectBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_IMMEDIATE_QUEUE);

        return $selectBuilder
            ->select('*')
            ->from(Configuration::TABLE_IMMEDIATE_QUEUE)
            ->where($selectBuilder->expr()->eq('claim_token', $selectBuilder->createNamedParameter($token)))
            ->orderBy('crdate', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Marks a successfully-sent claim as actually sent. Deliberately separate from
     * {@see self::claimPending()}: `sent_at` only becomes non-null once the mail transport call
     * has returned without throwing, so a transport failure leaves the claim reclaimable (once
     * its lease expires) rather than silently dropping the mail.
     *
     * @param list<int> $uids
     *
     * @throws Exception
     */
    public function markSent(array $uids, int $sentAt): void
    {
        if ([] === $uids) {
            return;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_IMMEDIATE_QUEUE);
        $queryBuilder
            ->update(Configuration::TABLE_IMMEDIATE_QUEUE)
            ->set('sent_at', $sentAt)
            ->where(
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)),
            )
            ->executeStatement();
    }

    /**
     * Distinct `(backend_user, tablename, record_uid)` triples that still have at least one
     * unsent row - candidates for {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\Immediate\ImmediateEmailService::flushDueQueues()}
     * to re-check, since a triple stuck behind the per-record throttle or the recipient's hourly
     * cap otherwise only ever gets re-examined by the next live event on that same record, which
     * may never arrive.
     *
     * @return list<array{backend_user: int, tablename: string, record_uid: int}>
     *
     * @throws Exception
     */
    public function findDistinctPendingTriples(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_IMMEDIATE_QUEUE);

        $rows = $queryBuilder
            ->selectLiteral('DISTINCT '.$queryBuilder->quoteIdentifier('backend_user'), $queryBuilder->quoteIdentifier('tablename'), $queryBuilder->quoteIdentifier('record_uid'))
            ->from(Configuration::TABLE_IMMEDIATE_QUEUE)
            ->where($queryBuilder->expr()->isNull('sent_at'))
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(
            static fn (array $row): array => [
                'backend_user' => (int) $row['backend_user'],
                'tablename' => (string) $row['tablename'],
                'record_uid' => (int) $row['record_uid'],
            ],
            $rows,
        );
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

        return $queryBuilder
            ->delete(Configuration::TABLE_IMMEDIATE_QUEUE)
            ->where(...$constraints)
            ->executeStatement();
    }

    /**
     * How many *mails* (not queue rows) this recipient has already been sent since $since,
     * across all records. The per-record throttle alone does not bound the total: changing many
     * different watched records in quick succession produces one mail each.
     *
     * Counts `DISTINCT sent_at` rather than rows: {@see self::markSent()} stamps every row in one
     * batched send with the same `sent_at` value, so counting rows would over-count a single
     * multi-event mail as several. Two distinct sends for the same recipient landing in the same
     * second would under-count by one in that narrow case - an accepted approximation, consistent
     * with this class's existing second-granularity throttle window.
     *
     * @throws Exception
     */
    public function countSentSince(int $backendUserUid, int $since): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_IMMEDIATE_QUEUE);

        return (int) $queryBuilder
            ->selectLiteral('COUNT(DISTINCT '.$queryBuilder->quoteIdentifier('sent_at').') AS mail_count')
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
