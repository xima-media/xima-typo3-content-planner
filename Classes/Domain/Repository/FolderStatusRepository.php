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

use Doctrine\DBAL\{ArrayParameterType, Exception};
use InvalidArgumentException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Database\{Connection, ConnectionPool};
use TYPO3\CMS\Core\Resource\ResourceFactory;
use Xima\XimaTypo3ContentPlanner\Configuration;

use function count;
use function hash;
use function is_array;
use function is_int;
use function sprintf;

/**
 * FolderStatusRepository.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class FolderStatusRepository
{
    private const TABLE = Configuration::TABLE_FOLDER;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ResourceFactory $resourceFactory,
        private readonly FrontendInterface $cache,
    ) {}

    /**
     * Find folder status by combined identifier (e.g., "1:/user_upload/").
     *
     * Mirrors RecordRepository's cache scheme: identifier "<CACHE_IDENTIFIER>--<table>--<hash>"
     * (hashed because a combined identifier contains ':' and '/', which the cache frontend's
     * identifier pattern rejects), tagged per row plus "<table>__storage__<storageUid>" so a
     * write only needs to know the affected storage to invalidate every folder cached under it.
     * The hash also folds in the storage's generation counter (see cacheIdentifierFor()) to
     * prevent a stale cache-aside write from resurrecting a row after a concurrent invalidation.
     *
     * @return array<string, mixed>|false
     *
     * @throws Exception
     */
    public function findByCombinedIdentifier(string $combinedIdentifier): array|false
    {
        $parsed = $this->parseCombinedIdentifier($combinedIdentifier);
        if (null === $parsed) {
            return false;
        }

        $cacheIdentifier = $this->cacheIdentifierFor($combinedIdentifier, $parsed['storageUid']);
        $cachedResult = $this->cache->get($cacheIdentifier);
        if (is_array($cachedResult)) {
            return $cachedResult;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);

        $result = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'storage_uid',
                    $queryBuilder->createNamedParameter($parsed['storageUid'], Connection::PARAM_INT),
                ),
                $queryBuilder->expr()->eq(
                    'folder_identifier',
                    $queryBuilder->createNamedParameter($parsed['path']),
                ),
                $queryBuilder->expr()->eq('deleted', 0),
            )
            ->executeQuery()
            ->fetchAssociative();

        if (is_array($result)) {
            $this->cache->set($cacheIdentifier, $result, $this->collectCacheTags($result));
        }

        return $result;
    }

    /**
     * Batch-resolve folder status rows for several combined identifiers.
     *
     * Cache misses are queried per storage in a single IN() query, but every identifier is
     * first served from (and afterwards written back to) the same per-identifier cache entries
     * that findByCombinedIdentifier() uses, so the two methods never diverge on freshness.
     *
     * @param array<int, string> $combinedIdentifiers
     *
     * @return array<string, array<string, mixed>> map keyed by combined identifier
     *
     * @throws Exception
     */
    public function findByCombinedIdentifiers(array $combinedIdentifiers): array
    {
        $result = [];

        // Group requested paths per storage so each storage needs a single IN() query,
        // skipping everything already served from cache.
        $pathsByStorage = [];
        foreach ($combinedIdentifiers as $combinedIdentifier) {
            $parsed = $this->parseCombinedIdentifier($combinedIdentifier);
            if (null === $parsed) {
                continue;
            }

            $cached = $this->cache->get($this->cacheIdentifierFor($combinedIdentifier, $parsed['storageUid']));
            if (is_array($cached)) {
                $result[$combinedIdentifier] = $cached;
                continue;
            }

            $pathsByStorage[$parsed['storageUid']][$parsed['path']] = $combinedIdentifier;
        }

        foreach ($pathsByStorage as $storageUid => $pathMap) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
            $rows = $queryBuilder
                ->select('*')
                ->from(self::TABLE)
                ->where(
                    $queryBuilder->expr()->eq(
                        'storage_uid',
                        $queryBuilder->createNamedParameter($storageUid, Connection::PARAM_INT),
                    ),
                    $queryBuilder->expr()->in(
                        'folder_identifier',
                        $queryBuilder->createNamedParameter(array_keys($pathMap), ArrayParameterType::STRING),
                    ),
                    $queryBuilder->expr()->eq('deleted', 0),
                )
                ->executeQuery()
                ->fetchAllAssociative();

            foreach ($rows as $row) {
                $combinedIdentifier = $pathMap[$row['folder_identifier']] ?? null;
                if (null !== $combinedIdentifier) {
                    $result[$combinedIdentifier] = $row;
                    $this->cache->set($this->cacheIdentifierFor($combinedIdentifier, $storageUid), $row, $this->collectCacheTags($row));
                }
            }
        }

        return $result;
    }

    /**
     * Find folder status by its UID.
     *
     * @return array<string, mixed>|false
     *
     * @throws Exception
     */
    public function findByUid(int $uid): array|false
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);

        return $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT),
                ),
                $queryBuilder->expr()->eq('deleted', 0),
            )
            ->executeQuery()
            ->fetchAssociative();
    }

    /**
     * Find all subfolders with status in a parent folder.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function findSubfoldersWithStatus(string $combinedIdentifier): array
    {
        try {
            $resourceFactory = $this->resourceFactory;
            $folder = $resourceFactory->getFolderObjectFromCombinedIdentifier($combinedIdentifier);
            $subfolders = $folder->getSubfolders();
        } catch (\Exception) {
            return [];
        }

        $identifiers = [];
        foreach ($subfolders as $subfolder) {
            $identifiers[] = $subfolder->getStorage()->getUid().':'.$subfolder->getIdentifier();
        }
        $statusByIdentifier = $this->findByCombinedIdentifiers($identifiers);

        $results = [];
        foreach ($subfolders as $subfolder) {
            $subfolderIdentifier = $subfolder->getStorage()->getUid().':'.$subfolder->getIdentifier();
            $status = $statusByIdentifier[$subfolderIdentifier] ?? null;
            if (null !== $status && null !== $status[Configuration::FIELD_STATUS] && 0 !== (int) $status[Configuration::FIELD_STATUS]) {
                $status['combined_identifier'] = $subfolderIdentifier;
                $status['title'] = $subfolder->getName();
                $results[] = $status;
            }
        }

        return $results;
    }

    /**
     * Get all subfolders in a parent folder with their status info.
     *
     * @return array<int, array{combined_identifier: string, title: string, status: array<string, mixed>|null}>
     */
    public function getAllSubfolders(string $combinedIdentifier): array
    {
        try {
            $resourceFactory = $this->resourceFactory;
            $folder = $resourceFactory->getFolderObjectFromCombinedIdentifier($combinedIdentifier);
            $subfolders = $folder->getSubfolders();
        } catch (\Exception) {
            return [];
        }

        $identifiers = [];
        foreach ($subfolders as $subfolder) {
            $identifiers[] = $subfolder->getStorage()->getUid().':'.$subfolder->getIdentifier();
        }
        $statusByIdentifier = $this->findByCombinedIdentifiers($identifiers);

        $results = [];
        foreach ($subfolders as $subfolder) {
            $subfolderIdentifier = $subfolder->getStorage()->getUid().':'.$subfolder->getIdentifier();
            $status = $statusByIdentifier[$subfolderIdentifier] ?? null;
            $results[] = [
                'combined_identifier' => $subfolderIdentifier,
                'title' => $subfolder->getName(),
                'status' => $status,
            ];
        }

        return $results;
    }

    /**
     * Create or update folder status record.
     * Returns the UID of the created/updated record.
     *
     * @throws Exception
     */
    public function createOrUpdate(string $combinedIdentifier, ?int $status, ?int $assignee = null): int
    {
        $existing = $this->findByCombinedIdentifier($combinedIdentifier);

        if ($existing) {
            // The storage UID is already part of the row we just fetched, so pass it on
            // instead of letting updateStatus() look it up again.
            $this->updateStatus((int) $existing['uid'], $status, $assignee ?? false, (int) $existing['storage_uid']);

            return (int) $existing['uid'];
        }

        return $this->create($combinedIdentifier, $status, $assignee);
    }

    /**
     * Create a new folder status record.
     *
     * @throws Exception
     */
    public function create(string $combinedIdentifier, ?int $status, ?int $assignee = null): int
    {
        $parsed = $this->parseCombinedIdentifier($combinedIdentifier);
        if (null === $parsed) {
            throw new InvalidArgumentException('Invalid combined identifier: '.$combinedIdentifier, 4239684855);
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder
            ->insert(self::TABLE)
            ->values([
                'pid' => 0,
                'storage_uid' => $parsed['storageUid'],
                'folder_identifier' => $parsed['path'],
                Configuration::FIELD_STATUS => $status,
                Configuration::FIELD_ASSIGNEE => $assignee,
                Configuration::FIELD_COMMENTS => 0,
                'tstamp' => time(),
                'crdate' => time(),
            ])
            ->executeStatement();

        // Capture the new row's uid before invalidateCacheForStorage() runs: its own cache
        // writes are themselves database inserts, and lastInsertId() reflects whichever insert
        // ran most recently on this connection - reading it after that call would return the
        // cache backend's row, not this method's.
        $uid = (int) $queryBuilder->getConnection()->lastInsertId();

        $this->invalidateCacheForStorage($parsed['storageUid']);

        return $uid;
    }

    /**
     * Update status for a folder status record.
     *
     * Pass $storageUid when the caller already knows it to save the lookup query;
     * it is only needed to invalidate the storage's cache entries after the write.
     *
     * @throws Exception
     */
    public function updateStatus(int $uid, ?int $status, int|bool|null $assignee = false, ?int $storageUid = null): void
    {
        $storageUid ??= $this->getStorageUidByUid($uid);

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder
            ->update(self::TABLE)
            ->set(Configuration::FIELD_STATUS, $status)
            ->set('tstamp', time())
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
            );

        if (false !== $assignee) {
            $queryBuilder->set(Configuration::FIELD_ASSIGNEE, $assignee);
        }

        $queryBuilder->executeStatement();

        if (null !== $storageUid) {
            $this->invalidateCacheForStorage($storageUid);
        }
    }

    /**
     * Update comments count for a folder status record.
     *
     * @throws Exception
     */
    public function updateCommentsCount(int $uid, int $count): void
    {
        $storageUid = $this->getStorageUidByUid($uid);

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder
            ->update(self::TABLE)
            ->set(Configuration::FIELD_COMMENTS, $count)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
            )
            ->executeStatement();

        if (null !== $storageUid) {
            $this->invalidateCacheForStorage($storageUid);
        }
    }

    /**
     * Get combined identifier from storage UID and folder identifier.
     */
    public function getCombinedIdentifier(int $storageUid, string $folderIdentifier): string
    {
        return $storageUid.':'.$folderIdentifier;
    }

    /**
     * Build the cache identifier for a combined identifier. The combined identifier itself
     * contains ':' and '/', which the cache frontend's entry identifier pattern rejects, so
     * it is hashed rather than used verbatim.
     *
     * The storage's current generation counter is mixed into the hash so that a write which
     * advances the generation (see invalidateCacheForStorage()) makes every identifier computed
     * before that write unreachable. This closes a cache-aside race: a read that fetched a now
     * stale row before a concurrent write, and only calls set() after that write's flushByTags()
     * already ran, would otherwise resurrect the stale row under the very same identifier. With
     * the generation folded in, that late set() lands on an identifier nothing will ever look up
     * again, so it is simply orphaned rather than served.
     */
    private function cacheIdentifierFor(string $combinedIdentifier, int $storageUid): string
    {
        $generation = $this->storageGeneration($storageUid);

        return sprintf('%s--%s--%s', Configuration::CACHE_IDENTIFIER, self::TABLE, hash('sha256', $combinedIdentifier.'--gen--'.$generation));
    }

    /**
     * Current generation counter for a storage's cached folder statuses, defaulting to 0 when
     * none has been recorded yet (fresh cache, or the counter was just flushed by a write).
     */
    private function storageGeneration(int $storageUid): int
    {
        $generation = $this->cache->get($this->storageGenerationCacheIdentifier($storageUid));

        return is_int($generation) ? $generation : 0;
    }

    /**
     * Cache identifier for a storage's generation counter. Tagged with the same storage tag as
     * every folder status row of that storage, so flushByTags() in invalidateCacheForStorage()
     * clears it together with the rows it guards, right before that method writes the next value.
     */
    private function storageGenerationCacheIdentifier(int $storageUid): string
    {
        return sprintf('%s--%s--gen--%d', Configuration::CACHE_IDENTIFIER, self::TABLE, $storageUid);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return string[]
     */
    private function collectCacheTags(array $row): array
    {
        return [
            self::TABLE.'_'.$row['uid'],
            self::TABLE.'__storage__'.$row['storage_uid'],
        ];
    }

    /**
     * Flush every cached folder status belonging to one storage. Write methods only ever know
     * the affected storage (not which combined identifiers are currently cached for it), so
     * invalidation is scoped to the storage tag rather than the individual cache entry.
     *
     * Advancing the storage's generation counter afterwards closes the cache-aside race where a
     * concurrent read, already past its DB fetch when this flush runs, would otherwise call
     * set() after the flush and resurrect the stale row it fetched. See cacheIdentifierFor().
     */
    private function invalidateCacheForStorage(int $storageUid): void
    {
        $storageTag = self::TABLE.'__storage__'.$storageUid;
        $this->cache->flushByTags([$storageTag]);

        $nextGeneration = $this->storageGeneration($storageUid) + 1;
        $this->cache->set($this->storageGenerationCacheIdentifier($storageUid), $nextGeneration, [$storageTag]);
    }

    /**
     * @throws Exception
     */
    private function getStorageUidByUid(int $uid): ?int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $row = $queryBuilder
            ->select('storage_uid')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchAssociative();

        return $row ? (int) $row['storage_uid'] : null;
    }

    /**
     * Parse combined identifier into storage UID and path.
     *
     * @return array{storageUid: int, path: string}|null
     */
    private function parseCombinedIdentifier(string $combinedIdentifier): ?array
    {
        if (!str_contains($combinedIdentifier, ':')) {
            return null;
        }

        $parts = explode(':', $combinedIdentifier, 2);
        if (2 !== count($parts)) {
            return null;
        }

        return [
            'storageUid' => (int) $parts[0],
            'path' => $parts[1],
        ];
    }
}
