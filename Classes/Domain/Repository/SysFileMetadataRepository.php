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
use TYPO3\CMS\Core\Resource\{File, ResourceFactory};
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;

/**
 * SysFileMetadataRepository.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class SysFileMetadataRepository
{
    private const TABLE = 'sys_file_metadata';

    public function __construct(
        private readonly ConnectionPool $connectionPool, private readonly \TYPO3\CMS\Core\Resource\ResourceFactory $resourceFactory,
    ) {}

    /**
     * Find metadata record by file UID.
     *
     * @return array<string, mixed>|false
     *
     * @throws Exception
     */
    public function findByFileUid(int $fileUid): array|false
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);

        return $queryBuilder
            ->select(
                'sys_file_metadata.uid',
                'sys_file.name as title',
                'sys_file.identifier',
                'sys_file.uid as file_uid',
                'sys_file_metadata.'.Configuration::FIELD_STATUS,
                'sys_file_metadata.'.Configuration::FIELD_ASSIGNEE,
                'sys_file_metadata.'.Configuration::FIELD_COMMENTS,
            )
            ->from(self::TABLE)
            ->innerJoin(
                'sys_file_metadata',
                'sys_file',
                'sys_file',
                'sys_file_metadata.file = sys_file.uid',
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'sys_file.uid',
                    $queryBuilder->createNamedParameter($fileUid, Connection::PARAM_INT),
                ),
            )
            ->executeQuery()
            ->fetchAssociative();
    }

    /**
     * Find metadata record by its UID.
     *
     * @return array<string, mixed>|false
     *
     * @throws Exception
     */
    public function findByUid(int $uid): array|false
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);

        return $queryBuilder
            ->select(
                'sys_file_metadata.uid',
                'sys_file.name as title',
                'sys_file.identifier',
                'sys_file.uid as file_uid',
                'sys_file_metadata.'.Configuration::FIELD_STATUS,
                'sys_file_metadata.'.Configuration::FIELD_ASSIGNEE,
                'sys_file_metadata.'.Configuration::FIELD_COMMENTS,
            )
            ->from(self::TABLE)
            ->innerJoin(
                'sys_file_metadata',
                'sys_file',
                'sys_file',
                'sys_file_metadata.file = sys_file.uid',
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'sys_file_metadata.uid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT),
                ),
            )
            ->executeQuery()
            ->fetchAssociative();
    }

    /**
     * Find metadata record by file identifier (e.g., "/user_upload/file.pdf").
     *
     * @return array<string, mixed>|false
     *
     * @throws Exception
     */
    public function findByIdentifier(string $identifier): array|false
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);

        return $queryBuilder
            ->select(
                'sys_file_metadata.uid',
                'sys_file.name as title',
                'sys_file.identifier',
                'sys_file.uid as file_uid',
                'sys_file_metadata.'.Configuration::FIELD_STATUS,
                'sys_file_metadata.'.Configuration::FIELD_ASSIGNEE,
                'sys_file_metadata.'.Configuration::FIELD_COMMENTS,
            )
            ->from(self::TABLE)
            ->innerJoin(
                'sys_file_metadata',
                'sys_file',
                'sys_file',
                'sys_file_metadata.file = sys_file.uid',
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'sys_file.identifier',
                    $queryBuilder->createNamedParameter($identifier),
                ),
            )
            ->executeQuery()
            ->fetchAssociative();
    }

    /**
     * Get all files in a folder using ResourceFactory.
     *
     * @return File[]
     */
    public function findFilesByFolder(string $combinedIdentifier): array
    {
        try {
            $resourceFactory = $this->resourceFactory;
            $folder = $resourceFactory->getFolderObjectFromCombinedIdentifier($combinedIdentifier);

            return $folder->getFiles();
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Find all metadata records with status for files in a folder.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function findByFolderWithStatus(string $combinedIdentifier): array
    {
        $files = $this->findFilesByFolder($combinedIdentifier);
        $results = [];

        foreach ($files as $file) {
            $metadata = $this->findByIdentifier($file->getIdentifier());
            if ($metadata && null !== $metadata[Configuration::FIELD_STATUS] && 0 !== (int) $metadata[Configuration::FIELD_STATUS]) {
                $results[] = $metadata;
            }
        }

        return $results;
    }

    /**
     * Update status for a metadata record.
     *
     * @throws Exception
     */
    public function updateStatus(int $uid, ?int $status, int|bool|null $assignee = false): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder
            ->update(self::TABLE)
            ->set(Configuration::FIELD_STATUS, $status)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
            );

        if (false !== $assignee) {
            $queryBuilder->set(Configuration::FIELD_ASSIGNEE, $assignee);
        }

        $queryBuilder->executeStatement();
    }
}
