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
        'x.uid',
        'x.pid',
        'x.tstamp',
        'x.tx_ximatypo3contentplanner_status',
        'x.tx_ximatypo3contentplanner_assignee',
        'x.tx_ximatypo3contentplanner_comments',
    ];

    public function __construct(
        private readonly FrontendInterface $cache,
        private readonly SysFileMetadataRepository $sysFileMetadataRepository,
    ) {
    }

    /*
    * @ToDo: This needs to be improved. The SQL is not very readable and the performance is not optimal.
    */
    public function findAllByFilter(?string $search = null, ?int $status = null, ?int $assignee = null, ?string $type = null, ?bool $todo = null, int $maxResults = 20): array|bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');

        $additionalWhere = '';
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

            $additionalWhereByTable = $additionalWhere;
            if ($this->hasDeletedRestriction($table)) {
                $additionalWhereByTable .= ' AND deleted = 0';
            }

            $additionalJoin = '';
            if ($table === 'sys_file_metadata') {
                $additionalJoin = ' LEFT JOIN sys_file f ON f.uid = x.file';
            }

            if ($todo) {
                // ToDo: Check for performance
                $subQueryTotal = "(SELECT SUM(todo_total) FROM tx_ximatypo3contentplanner_comment WHERE foreign_uid = x.uid AND foreign_table = '$table')";
                $subQueryResolved = "(SELECT SUM(todo_resolved) FROM tx_ximatypo3contentplanner_comment WHERE foreign_uid = x.uid AND foreign_table = '$table')";

                $additionalWhereByTable .= " AND ($subQueryTotal > 0) AND ($subQueryResolved < $subQueryTotal)";
            }

            $this->getSqlByTable($table, $sqlArray, $additionalWhereByTable, $additionalJoin);
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
    public function findByPid(string $table, ?int $pid = null, bool $orderByTstamp = true, bool $ignoreHiddenRestriction = false): array
    {
        $cacheIdentifier = sprintf('%s--%s--p%s', Configuration::CACHE_IDENTIFIER, $table, $pid);
        if ($this->cache->has($cacheIdentifier)) {
            return $this->cache->get($cacheIdentifier);
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);

        if ($ignoreHiddenRestriction) {
            $queryBuilder->getRestrictions()->removeByType(\TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction::class);
        }

        $query = $queryBuilder
            ->select('uid', $this->getTitleField($table) . ' as title', 'tx_ximatypo3contentplanner_status', 'tx_ximatypo3contentplanner_assignee', 'tx_ximatypo3contentplanner_comments')
            ->from($table)
            ->andWhere(
                $queryBuilder->expr()->isNotNull('tx_ximatypo3contentplanner_status'),
                $queryBuilder->expr()->neq('tx_ximatypo3contentplanner_status', 0)
            );

        if ($orderByTstamp) {
            $query->addOrderBy('tstamp', 'DESC');
        }

        if ($pid) {
            $query->andWhere(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, \TYPO3\CMS\Core\Database\Connection::PARAM_INT))
            );
        }

        if ($this->hasDeletedRestriction($table)) {
            $query->andWhere($queryBuilder->expr()->eq('deleted', 0));
        }

        $result = $query->executeQuery()
            ->fetchAllAssociative();

        if (!empty($result)) {
            $this->cache->set($cacheIdentifier, $result, $this->collectCacheTags($table, $result, $pid));
        }

        return $result;
    }

    /**
    * @throws \Doctrine\DBAL\Exception
    */
    public function findByUid(?string $table, ?int $uid, bool $ignoreHiddenRestriction = false): array|bool|null
    {
        if (!$table && !$uid) {
            return null;
        }
        if ($table === 'sys_file_metadata') {
            return $this->sysFileMetadataRepository->findByUid($uid);
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);

        if ($ignoreHiddenRestriction) {
            $queryBuilder->getRestrictions()->removeByType(\TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction::class);
        }

        $query = $queryBuilder
            ->select('uid', 'pid', $this->getTitleField($table) . ' as "title"', 'tx_ximatypo3contentplanner_status', 'tx_ximatypo3contentplanner_assignee', 'tx_ximatypo3contentplanner_comments')
            ->from($table)
            ->andWhere(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
            );

        if ($this->hasDeletedRestriction($table)) {
            $query->andWhere($queryBuilder->expr()->eq('deleted', 0));
        }

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

    private function getSqlByTable(string $table, array &$sql, string $additionalWhere, string $additionalJoin): void
    {
        $titleField = $this->getTitleField($table);

        switch ($table) {
            case 'pages':
                $selects = array_merge($this->defaultSelects, [$titleField . ' as title, "' . $table . '" as tablename', 'perms_userid', 'perms_groupid', 'perms_user', 'perms_group', 'perms_everybody']);
                break;
            case 'sys_file_metadata':
                $selects = array_merge($this->defaultSelects, ['f.name as title, "' . $table . '" as tablename', '0 as perms_userid', '0 as perms_groupid', '0 as perms_user', '0 as perms_group', '0 as perms_everybody']);
                break;
            default:
                $selects = array_merge($this->defaultSelects, [$titleField . ' as title, "' . $table . '" as tablename', '0 as perms_userid', '0 as perms_groupid', '0 as perms_user', '0 as perms_group', '0 as perms_everybody']);

                break;
        }

        $sql[] = '(SELECT ' . implode(',', $selects) . ' FROM ' . $table . ' x ' . $additionalJoin . ' WHERE tx_ximatypo3contentplanner_status IS NOT NULL AND tx_ximatypo3contentplanner_status != 0' . $additionalWhere . ')';
    }

    private function getTitleField(string $table): string
    {
        return $GLOBALS['TCA'][$table]['ctrl']['label'];
    }

    private function hasDeletedRestriction(string $table): bool
    {
        return isset($GLOBALS['TCA'][$table]['ctrl']['delete']);
    }

    private function collectCacheTags(string $table, array $data, ?int $pid): array
    {
        $tags = [];
        /* @var $item \TYPO3\CMS\Extbase\DomainObject\AbstractEntity */
        foreach ($data as $item) {
            if ($item['uid'] !== null) {
                $tags[] = $table . '_' . $item['uid'];
            }
        }

        if ($pid) {
            $tags[] = $table . '__pageId__' . $pid;
        }
        return $tags;
    }
}
