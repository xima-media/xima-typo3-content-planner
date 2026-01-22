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
use InvalidArgumentException;
use TYPO3\CMS\Core\Database\{Connection, ConnectionPool};
use TYPO3\CMS\Core\Resource\ResourceFactory;
use Xima\XimaTypo3ContentPlanner\Configuration;

use function count;

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
        private readonly ConnectionPool $connectionPool, private readonly ResourceFactory $resourceFactory,
    ) {}

    /**
     * Find folder status by combined identifier (e.g., "1:/user_upload/").
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

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);

        return $queryBuilder
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

        $results = [];
        foreach ($subfolders as $subfolder) {
            $subfolderIdentifier = $subfolder->getStorage()->getUid().':'.$subfolder->getIdentifier();
            $status = $this->findByCombinedIdentifier($subfolderIdentifier);
            if ($status && null !== $status[Configuration::FIELD_STATUS] && 0 !== (int) $status[Configuration::FIELD_STATUS]) {
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

        $results = [];
        foreach ($subfolders as $subfolder) {
            $subfolderIdentifier = $subfolder->getStorage()->getUid().':'.$subfolder->getIdentifier();
            $status = $this->findByCombinedIdentifier($subfolderIdentifier);
            $results[] = [
                'combined_identifier' => $subfolderIdentifier,
                'title' => $subfolder->getName(),
                'status' => $status ?: null,
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
            $this->updateStatus((int) $existing['uid'], $status, $assignee ?? false);

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

        return (int) $queryBuilder->getConnection()->lastInsertId();
    }

    /**
     * Update status for a folder status record.
     *
     * @throws Exception
     */
    public function updateStatus(int $uid, ?int $status, int|bool|null $assignee = false): void
    {
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
    }

    /**
     * Update comments count for a folder status record.
     *
     * @throws Exception
     */
    public function updateCommentsCount(int $uid, int $count): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder
            ->update(self::TABLE)
            ->set(Configuration::FIELD_COMMENTS, $count)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
            )
            ->executeStatement();
    }

    /**
     * Get combined identifier from storage UID and folder identifier.
     */
    public function getCombinedIdentifier(int $storageUid, string $folderIdentifier): string
    {
        return $storageUid.':'.$folderIdentifier;
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
