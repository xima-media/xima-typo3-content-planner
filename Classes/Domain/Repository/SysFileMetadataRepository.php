<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Domain\Repository;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

class SysFileMetadataRepository
{
    private const TABLE = 'sys_file_metadata';

    protected array $defaultOrderings = [
        'crdate' => QueryInterface::ORDER_DESCENDING,
    ];

    /**
    * @throws \Doctrine\DBAL\Exception
    */
    public function findByIdentifier(string $identifier): array|bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);
        return $queryBuilder
            ->select('sys_file_metadata.uid', 'sys_file.name as title', 'sys_file.identifier', 'sys_file.uid as fileUid', 'sys_file_metadata.tx_ximatypo3contentplanner_status', 'sys_file_metadata.tx_ximatypo3contentplanner_assignee', 'sys_file_metadata.tx_ximatypo3contentplanner_comments')
            ->from('sys_file_metadata')
            ->innerJoin('sys_file_metadata', 'sys_file', 'sys_file', 'sys_file_metadata.file = sys_file.uid')
            ->andWhere(
                $queryBuilder->expr()->eq('sys_file.identifier', $queryBuilder->createNamedParameter($identifier, \TYPO3\CMS\Core\Database\Connection::PARAM_STR))
            )->executeQuery()
            ->fetchAssociative();
    }

    /**
    * @throws \Doctrine\DBAL\Exception
    */
    public function findByUid(int $uid): array|bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);
        return $queryBuilder
            ->select('sys_file_metadata.uid', 'sys_file.name as title', 'sys_file.identifier', 'sys_file.uid as fileUid', 'sys_file_metadata.tx_ximatypo3contentplanner_status', 'sys_file_metadata.tx_ximatypo3contentplanner_assignee', 'sys_file_metadata.tx_ximatypo3contentplanner_comments')
            ->from('sys_file_metadata')
            ->innerJoin('sys_file_metadata', 'sys_file', 'sys_file', 'sys_file_metadata.file = sys_file.uid')
            ->andWhere(
                $queryBuilder->expr()->eq('sys_file_metadata.uid', $queryBuilder->createNamedParameter($uid, \TYPO3\CMS\Core\Database\Connection::PARAM_INT))
            )->executeQuery()
            ->fetchAssociative();
    }

    public function findFilesByFolder(string $folderIdentifier): array
    {
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $folder = $resourceFactory->getFolderObjectFromCombinedIdentifier($folderIdentifier);
        return $folder->getFiles();
    }
}
