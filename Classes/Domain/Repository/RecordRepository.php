<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Domain\Repository;

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
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

    public function __construct(private readonly FrontendInterface $cache)
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
        $results = $statement->fetchAllAssociative();

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
    public function findByPid(string $table, ?int $pid = null, bool $orderByTstamp = true): array
    {
        $cacheIdentifier = sprintf('%s--%s--p%s', Configuration::CACHE_IDENTIFIER, $table, $pid);
        if ($this->cache->has($cacheIdentifier)) {
            return $this->cache->get($cacheIdentifier);
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $query = $queryBuilder
            ->select('uid', $this->getTitleField($table) . ' as title', 'tx_ximatypo3contentplanner_status', 'tx_ximatypo3contentplanner_assignee', 'tx_ximatypo3contentplanner_comments')
            ->from($table)
            ->andWhere(
                $queryBuilder->expr()->isNotNull('tx_ximatypo3contentplanner_status'),
                $queryBuilder->expr()->neq('tx_ximatypo3contentplanner_status', 0),
                $queryBuilder->expr()->eq('deleted', 0)
            );

        if ($orderByTstamp) {
            $query->addOrderBy('tstamp', 'DESC');
        }

        if ($pid) {
            $query->andWhere(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, \TYPO3\CMS\Core\Database\Connection::PARAM_INT))
            );
        }

        $result = $query->executeQuery()
            ->fetchAllAssociative();

        $this->cache->set($cacheIdentifier, $result, $this->collectCacheTags($table, $result));
        return $result;
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
            ->select('uid', 'pid', $this->getTitleField($table) . ' as "title"', 'tx_ximatypo3contentplanner_status', 'tx_ximatypo3contentplanner_assignee', 'tx_ximatypo3contentplanner_comments')
            ->from($table)
            ->andWhere(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', 0)
            );

        return $query->executeQuery()
            ->fetchAssociative();
    }

    public function updateStatusByUid(string $table, int $uid, ?int $status, int|bool|null $assignee = false): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder
            ->update($table)
            ->set('tx_ximatypo3contentplanner_status', $status)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \TYPO3\CMS\Core\Database\Connection::PARAM_INT))
            );

        if ($assignee !== false) {
            $queryBuilder->set('tx_ximatypo3contentplanner_assignee', $assignee);
        }
        $queryBuilder->executeStatement();
    }

    public function updateCommentsRelationByRecord(string $table, int $uid): void
    {
        $commentRepository = GeneralUtility::makeInstance(CommentRepository::class);
        $commentCount = $commentRepository->countAllByRecord($uid, $table);

        $record = $this->findByUid($table, $uid);
        if ($record) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
            $queryBuilder
                ->update($table)
                ->set('tx_ximatypo3contentplanner_comments', $commentCount)
                ->where(
                    $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \TYPO3\CMS\Core\Database\Connection::PARAM_INT))
                )
                ->executeStatement();
        }
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

    private function collectCacheTags(string $table, array $data): array
    {
        $tags = [];
        /* @var $item \TYPO3\CMS\Extbase\DomainObject\AbstractEntity */
        foreach ($data as $item) {
            if ($item->getUid() !== null) {
                $tags[] = $table . '_' . $item->getUid();
            }
        }
        return $tags;
    }
}
