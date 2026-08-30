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
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use TYPO3\CMS\Core\Database\{Connection, ConnectionPool};
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\{WatchMode, WatchSource};

use function is_array;
use function is_string;

/**
 * WatcherRepository.
 *
 * Persistence for `tx_ximatypo3contentplanner_watcher`. Writes go through raw QueryBuilder
 * operations (this table is never edited via FormEngine/DataHandler), following the same
 * convention as {@see RecordRepository::updateStatusByUid()}.
 *
 * Known gaps, both deliberate at this point in the notifications epic:
 *
 * - Nothing deletes rows. "Unwatching" flips `mode` to `manual_unwatch` rather than removing
 *   the row, so the table only ever grows. Retention is introduced with the cleanup command
 *   (#304).
 * - Deleting the watched record or the backend user leaves the row behind; there is no
 *   foreign key and no delete listener. Orphan cleanup arrives with the same command, so
 *   until then a resolved watcher list can contain rows pointing at records that are gone.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class WatcherRepository
{
    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /**
     * @return array<string, mixed>|false
     *
     * @throws Exception
     */
    public function findByRecordAndUser(string $table, int $uid, int $beUser): array|false
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_WATCHER);

        $row = $queryBuilder
            ->select('uid', 'mode', 'source')
            ->from(Configuration::TABLE_WATCHER)
            ->where(
                $queryBuilder->expr()->eq('tablename', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->eq('record_uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('backend_user', $queryBuilder->createNamedParameter($beUser, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $row : false;
    }

    /**
     * @throws Exception
     */
    public function findMode(string $table, int $uid, int $beUser): ?WatchMode
    {
        $row = $this->findByRecordAndUser($table, $uid, $beUser);
        if (false === $row || !is_string($row['mode'])) {
            return null;
        }

        return WatchMode::from($row['mode']);
    }

    /**
     * Active watchers for a record, i.e. every backend user whose relation is not
     * {@see WatchMode::ManualUnwatch}.
     *
     * @return array<int, int> backend user UIDs
     *
     * @throws Exception
     */
    public function findActiveWatcherUserIds(string $table, int $uid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_WATCHER);

        $rows = $queryBuilder
            ->select('backend_user')
            ->from(Configuration::TABLE_WATCHER)
            ->where(
                $queryBuilder->expr()->eq('tablename', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->eq('record_uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
                $queryBuilder->expr()->neq('mode', $queryBuilder->createNamedParameter(WatchMode::ManualUnwatch->value)),
            )
            ->executeQuery()
            ->fetchFirstColumn();

        return array_map(intval(...), $rows);
    }

    /**
     * Upsert for automatic (non-manual) triggers, which must never overwrite an explicit
     * decision the user made.
     *
     * Doing that as read-mode-then-upsert is not safe: a manual watch or unwatch landing
     * between the two would be silently replaced by `auto`. The condition therefore lives in
     * the UPDATE itself, so only an already-automatic row is touched, and the insert for the
     * "no row yet" case relies on the unique key to lose the race against a manual write.
     *
     * @throws Exception
     */
    public function upsertAutomatic(string $table, int $uid, int $beUser, WatchSource $source): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_WATCHER);
        $updated = $queryBuilder
            ->update(Configuration::TABLE_WATCHER)
            ->set('source', $source->value)
            ->set('tstamp', time())
            ->where(
                $queryBuilder->expr()->eq('tablename', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->eq('record_uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('backend_user', $queryBuilder->createNamedParameter($beUser, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('mode', $queryBuilder->createNamedParameter(WatchMode::Auto->value)),
            )
            ->executeStatement();

        if ($updated > 0) {
            return;
        }

        try {
            $this->insertRow($table, $uid, $beUser, WatchMode::Auto, $source);
        } catch (UniqueConstraintViolationException) {
            // A row already exists and the UPDATE above did not match it, so it carries a
            // manual decision. Leave it exactly as the user set it.
        }
    }

    /**
     * Active watchers for a record together with the {@see WatchSource} that explains why each
     * one is watching, i.e. every backend user whose relation is not {@see WatchMode::ManualUnwatch}.
     * Used by the notification dispatcher (issue #300) to derive a per-recipient "why you receive
     * this" reason without a second query per watcher.
     *
     * @return array<int, WatchSource> keyed by backend user UID
     *
     * @throws Exception
     */
    public function findActiveWatchersWithSource(string $table, int $uid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_WATCHER);

        $rows = $queryBuilder
            ->select('backend_user', 'source')
            ->from(Configuration::TABLE_WATCHER)
            ->where(
                $queryBuilder->expr()->eq('tablename', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->eq('record_uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
                $queryBuilder->expr()->neq('mode', $queryBuilder->createNamedParameter(WatchMode::ManualUnwatch->value)),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $watchers = [];
        foreach ($rows as $row) {
            $watchers[(int) $row['backend_user']] = WatchSource::from((string) $row['source']);
        }

        return $watchers;
    }

    /**
     * Every record this backend user actively watches, keyed by table name with a list of record
     * UIDs, i.e. every relation that is not {@see WatchMode::ManualUnwatch}. Backs the "Watched by
     * me" widget filter (issue #308): a single query across the whole watcher table, independent
     * of any specific (table, uid) pair, unlike {@see self::findActiveWatcherUserIds()}.
     *
     * @return array<string, list<int>>
     *
     * @throws Exception
     */
    public function findActiveWatchedRecordsByUser(int $beUser): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_WATCHER);

        $rows = $queryBuilder
            ->select('tablename', 'record_uid')
            ->from(Configuration::TABLE_WATCHER)
            ->where(
                $queryBuilder->expr()->eq('backend_user', $queryBuilder->createNamedParameter($beUser, Connection::PARAM_INT)),
                $queryBuilder->expr()->neq('mode', $queryBuilder->createNamedParameter(WatchMode::ManualUnwatch->value)),
            )
            // Without this the database is free to vary the row order, which would make both
            // the grouped result and the IN() list it ends up in unstable between calls.
            ->orderBy('tablename', 'ASC')
            ->addOrderBy('record_uid', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        $watchedRecords = [];
        foreach ($rows as $row) {
            $watchedRecords[(string) $row['tablename']][] = (int) $row['record_uid'];
        }

        return $watchedRecords;
    }

    /**
     * Insert-or-update the watcher relation for (table, uid, beUser): a single row always exists
     * per unique triple once any trigger has fired for it (see the `watcher_lookup` unique key in
     * ext_tables.sql). Not implemented as a native DB-level upsert (not portable across the
     * MySQL/SQLite targets this extension supports) but as a read-then-write. Two concurrent
     * upserts for the same triple can both read "no existing row" and both attempt an insert;
     * the unique key then rejects the second one, which is caught and turned into an update so
     * the "single row" guarantee holds even under that race.
     *
     * @throws Exception
     */
    public function upsert(string $table, int $uid, int $beUser, WatchMode $mode, WatchSource $source): void
    {
        $existing = $this->findByRecordAndUser($table, $uid, $beUser);

        if (false !== $existing) {
            $this->updateRow((int) $existing['uid'], $mode, $source);

            return;
        }

        try {
            $this->insertRow($table, $uid, $beUser, $mode, $source);
        } catch (UniqueConstraintViolationException) {
            $existing = $this->findByRecordAndUser($table, $uid, $beUser);
            if (false !== $existing) {
                $this->updateRow((int) $existing['uid'], $mode, $source);
            }
        }
    }

    /**
     * Resolve a record UID to its default-language UID (via the table's TCA
     * `transOrigPointerField`/`languageField`), so watchers are always stored against the
     * default-language record. Tables without localization TCA (or a record already in the
     * default language) are returned unchanged.
     *
     * @throws Exception
     */
    public function normalizeToDefaultLanguageUid(string $table, int $uid): int
    {
        $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'] ?? null;
        $transOrigPointerField = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] ?? null;

        if (!is_string($languageField) || !is_string($transOrigPointerField)) {
            return $uid;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $row = $queryBuilder
            ->select($languageField, $transOrigPointerField)
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchAssociative();

        if (!is_array($row)) {
            return $uid;
        }

        $languageUid = (int) ($row[$languageField] ?? 0);
        $parentUid = (int) ($row[$transOrigPointerField] ?? 0);

        return $languageUid > 0 && $parentUid > 0 ? $parentUid : $uid;
    }

    /**
     * @throws Exception
     */
    private function updateRow(int $watcherUid, WatchMode $mode, WatchSource $source): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_WATCHER);
        $queryBuilder
            ->update(Configuration::TABLE_WATCHER)
            ->set('mode', $mode->value)
            ->set('source', $source->value)
            ->set('tstamp', time())
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($watcherUid, Connection::PARAM_INT)),
            )
            ->executeStatement();
    }

    /**
     * @throws Exception
     */
    private function insertRow(string $table, int $uid, int $beUser, WatchMode $mode, WatchSource $source): void
    {
        $now = time();
        $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_WATCHER)
            ->insert(Configuration::TABLE_WATCHER)
            ->values([
                'pid' => 0,
                'tablename' => $table,
                'record_uid' => $uid,
                'backend_user' => $beUser,
                'mode' => $mode->value,
                'source' => $source->value,
                'crdate' => $now,
                'tstamp' => $now,
            ])
            ->executeStatement();
    }
}
