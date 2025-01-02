<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Domain\Repository;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\PermissionUtility;

class RecordRepository
{
    private array $defaultSelects = [
        'uid',
        'pid',
        'tstamp',
        'tx_ximatypo3contentplanner_status',
        'tx_ximatypo3contentplanner_assignee',
        'tx_ximatypo3contentplanner_comments',
    ];

    public function __construct()
    {
    }

    public function findAllByFilter(?string $search = null, ?int $status = null, ?int $assignee = null, ?string $type = null, int $maxResults = 20): array|bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');

        $additionalWhere = ' AND deleted = 0';
        $additionalParams = [
            'limit' => $maxResults,
        ];

        if ($search) {
            $additionalWhere .= ' AND (title LIKE :search OR uid = :uid)';
            $additionalParams['search'] = '%' . $search . '%';
            $additionalParams['uid'] = $search;
        }

        if ($status) {
            $additionalWhere .= ' AND tx_ximatypo3contentplanner_status = :status';
            $additionalParams['status'] = $status;
        }

        if ($assignee) {
            $additionalWhere .= ' AND tx_ximatypo3contentplanner_assignee = :assignee';
            $additionalParams['assignee'] = $assignee;
        }

        $sqlArray = [];

        foreach (ExtensionUtility::getRecordTables() as $table) {
            if ($type && $type !== $table) {
                continue;
            }

            $this->getSqlByTable($table, $sqlArray, $additionalWhere);
        }

        $sql = implode(' UNION ', $sqlArray) . ' ORDER BY tstamp DESC LIMIT :limit';

        $statement = $queryBuilder->getConnection()->executeQuery($sql, $additionalParams);
        $results =  $statement->fetchAllAssociative();

        foreach ($results as $key => $record) {
            if (!PermissionUtility::checkAccessForRecord($record['tablename'], $record)) {
                unset($results[$key]);
            }
        }
        return $results;
    }

    /**
    * @throws \Doctrine\DBAL\Exception
    */
    public function findByPid(string $table, ?int $pid = null): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);

        $query = $queryBuilder
            ->select('uid', $this->getTitleField($table) . ' as "title"', 'tx_ximatypo3contentplanner_status', 'tx_ximatypo3contentplanner_assignee', 'tx_ximatypo3contentplanner_comments')
            ->from($table)
            ->andWhere(
                $queryBuilder->expr()->isNotNull('tx_ximatypo3contentplanner_status'),
                $queryBuilder->expr()->eq('deleted', 0)
            )
            ->orderBy('tstamp', 'DESC');

        if ($pid) {
            $query->andWhere(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, \TYPO3\CMS\Core\Database\Connection::PARAM_INT))
            );
        }

        return $query->executeQuery()
            ->fetchAllAssociative();
    }

    /**
    * @throws \Doctrine\DBAL\Exception
    */
    public function findByUid(?string $table, ?int $uid): array|bool|null
    {
        if (!$table && !$uid) {
            return null;
        }
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);

        $query = $queryBuilder
            ->select('uid', $this->getTitleField($table) . ' as "title"', 'tx_ximatypo3contentplanner_status', 'tx_ximatypo3contentplanner_assignee', 'tx_ximatypo3contentplanner_comments')
            ->from($table)
            ->andWhere(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', 0)
            );

        return $query->executeQuery()
            ->fetchAssociative();
    }

    private function getSqlByTable(string $table, array &$sql, string $additionalWhere): void
    {
        $titleField = $this->getTitleField($table);

        if ($table === 'pages') {
            $selects = array_merge($this->defaultSelects, [$titleField . ' as title, "' . $table . '" as tablename', 'perms_userid', 'perms_groupid', 'perms_user', 'perms_group', 'perms_everybody']);
        } else {
            $selects = array_merge($this->defaultSelects, [$titleField . ' as title, "' . $table . '" as tablename', '0 as perms_userid', '0 as perms_groupid', '0 as perms_user', '0 as perms_group', '0 as perms_everybody']);
        }

        $sql[] = '(SELECT ' . implode(',', $selects) . ' FROM ' . $table . ' WHERE tx_ximatypo3contentplanner_status IS NOT NULL AND tx_ximatypo3contentplanner_status != 0' . $additionalWhere . ')';
    }

    private function getTitleField(string $table): string
    {
        return $GLOBALS['TCA'][$table]['ctrl']['label'];
    }
}
