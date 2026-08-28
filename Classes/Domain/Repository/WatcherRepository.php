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
