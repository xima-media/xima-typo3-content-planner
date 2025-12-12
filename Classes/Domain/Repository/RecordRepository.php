<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Domain\Repository;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Database\{Connection, ConnectionPool};
use TYPO3\CMS\Core\Database\Query\Restriction\{EndTimeRestriction, HiddenRestriction, StartTimeRestriction};
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function count;
use function is_array;
use function sprintf;

/**
 * RecordRepository.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class RecordRepository
{
    /** @var string[] */
    private array $defaultSelects = [
        'uid',
        'pid',
        'tstamp',
        Configuration::FIELD_STATUS,
        Configuration::FIELD_ASSIGNEE,
        Configuration::FIELD_COMMENTS,
    ];

    public function __construct(private readonly FrontendInterface $cache, private readonly ConnectionPool $connectionPool) {}

    /**
     * @return array<int, array<string, mixed>>|bool
     *
     * @throws Exception
     */
    public function findAllByFilter(?string $search = null, ?int $status = null, ?int $assignee = null, ?string $type = null, ?bool $todo = null, int $maxResults = 20): array|bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');

        $baseWhere = '';
        $additionalParams = ['limit' => $maxResults];

        $this->applyFilterConditions($baseWhere, $additionalParams, $search, $status, $assignee);

        $sqlArray = $this->buildUnionQueriesForTables($baseWhere, $type, $todo, $search);
        $sql = implode(' UNION ', $sqlArray).' ORDER BY tstamp DESC LIMIT :limit';

        $statement = $queryBuilder->getConnection()->executeQuery($sql, $additionalParams);
        $results = $statement->fetchAllAssociative();

        return $this->filterResultsByPermission($results);
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function findByPid(string $table, ?int $pid = null, bool $orderByTstamp = true, bool $ignoreVisibilityRestriction = false): array
    {
        $cacheIdentifier = sprintf('%s--%s--p%s', Configuration::CACHE_IDENTIFIER, $table, $pid);
        $cachedResult = $this->cache->get($cacheIdentifier);
        if (is_array($cachedResult)) {
            return $cachedResult;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);

        if ($ignoreVisibilityRestriction) {
            $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);
            $queryBuilder->getRestrictions()->removeByType(StartTimeRestriction::class);
            $queryBuilder->getRestrictions()->removeByType(EndTimeRestriction::class);
        }

        $query = $queryBuilder
            ->select('uid', $this->getTitleField($table).' as title', Configuration::FIELD_STATUS, Configuration::FIELD_ASSIGNEE, Configuration::FIELD_COMMENTS)
            ->from($table)
            ->andWhere(
                $queryBuilder->expr()->isNotNull(Configuration::FIELD_STATUS),
                $queryBuilder->expr()->neq(Configuration::FIELD_STATUS, 0),
            );

        if ($this->hasDeletedRestriction($table)) {
            $query->andWhere($queryBuilder->expr()->eq('deleted', 0));
        }

        if ($orderByTstamp) {
            $query->addOrderBy('tstamp', 'DESC');
        }

        if ((bool) $pid) {
            $query->andWhere(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)),
            );
        }

        $result = $query->executeQuery()
            ->fetchAllAssociative();

        if (count($result) > 0) {
            $this->cache->set($cacheIdentifier, $result, $this->collectCacheTags($table, $result, $pid));
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|bool|null
     *
     * @throws Exception
     */
    public function findByUid(?string $table, ?int $uid, bool $ignoreVisibilityRestriction = false): array|bool|null
    {
        if (!(bool) $table && !(bool) $uid) {
            return null;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);

        if ($ignoreVisibilityRestriction) {
            $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);
            $queryBuilder->getRestrictions()->removeByType(StartTimeRestriction::class);
            $queryBuilder->getRestrictions()->removeByType(EndTimeRestriction::class);
        }

        $query = $queryBuilder
            ->select('uid', 'pid', $this->getTitleField($table).' as "title"', Configuration::FIELD_STATUS, Configuration::FIELD_ASSIGNEE, Configuration::FIELD_COMMENTS)
            ->from($table)
            ->andWhere(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
            );

        if ($this->hasDeletedRestriction($table)) {
            $query->andWhere($queryBuilder->expr()->eq('deleted', 0));
        }

        return $query->executeQuery()
            ->fetchAssociative();
    }

    public function updateStatusByUid(string $table, int $uid, ?int $status, int|bool|null $assignee = false): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder
            ->update($table)
            ->set(Configuration::FIELD_STATUS, $status)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
            );

        if (false !== $assignee) {
            $queryBuilder->set(Configuration::FIELD_ASSIGNEE, $assignee);
        }
        $queryBuilder->executeStatement();
    }

    /**
     * @throws Exception
     */
    public function updateCommentsRelationByRecord(string $table, int $uid): void
    {
        $commentRepository = GeneralUtility::makeInstance(CommentRepository::class);
        $commentCount = $commentRepository->countAllByRecord($uid, $table);

        $record = $this->findByUid($table, $uid);
        if ($record) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
            $queryBuilder
                ->update($table)
                ->set(Configuration::FIELD_COMMENTS, $commentCount)
                ->where(
                    $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
                )
                ->executeStatement();
        }
    }

    /**
     * @param array<string, mixed> $additionalParams
     */
    private function applyFilterConditions(string &$baseWhere, array &$additionalParams, ?string $search, ?int $status, ?int $assignee): void
    {
        // Note: search filter is applied per table in buildSearchCondition() due to different title fields

        if ((bool) $search) {
            $additionalParams['search'] = '%'.$search.'%';
            $additionalParams['uid'] = $search;
        }

        if ((bool) $status) {
            $baseWhere .= ' AND x.tx_ximatypo3contentplanner_status = :status';
            $additionalParams['status'] = $status;
        }

        if ((bool) $assignee) {
            $baseWhere .= ' AND x.tx_ximatypo3contentplanner_assignee = :assignee';
            $additionalParams['assignee'] = $assignee;
        }
    }

    /**
     * Build search condition for a specific table using its actual title field.
     */
    private function buildSearchCondition(string $table, ?string $search): string
    {
        if (!(bool) $search) {
            return '';
        }

        $titleField = $this->getTitleFieldForSearch($table);

        return ' AND ('.$titleField.' LIKE :search OR x.uid = :uid)';
    }

    /**
     * Get the title field for search queries, handling special cases.
     */
    private function getTitleFieldForSearch(string $table): string
    {
        if ('sys_file_metadata' === $table) {
            return 'f.name';
        }

        if (Configuration::TABLE_FOLDER === $table) {
            return 'folder_identifier';
        }

        return $this->getTitleField($table);
    }

    /**
     * @return string[]
     */
    private function buildUnionQueriesForTables(string $baseWhere, ?string $type, ?bool $todo, ?string $search = null): array
    {
        $sqlArray = [];

        foreach (ExtensionUtility::getRecordTables() as $table) {
            if ((bool) $type && $type !== $table) {
                continue;
            }

            $whereClause = $this->buildWhereClauseForTable($baseWhere, $table, $todo);
            $whereClause .= $this->buildSearchCondition($table, $search);
            $this->getSqlByTable($table, $sqlArray, $whereClause);
        }

        return $sqlArray;
    }

    private function buildWhereClauseForTable(string $baseWhere, string $table, ?bool $todo): string
    {
        if (!$todo) {
            return $baseWhere;
        }

        // ToDo: Check for performance
        $subQueryTotal = "(SELECT SUM(todo_total) FROM tx_ximatypo3contentplanner_comment WHERE foreign_uid = x.uid AND foreign_table = '$table')";
        $subQueryResolved = "(SELECT SUM(todo_resolved) FROM tx_ximatypo3contentplanner_comment WHERE foreign_uid = x.uid AND foreign_table = '$table')";

        return $baseWhere." AND ($subQueryTotal > 0) AND ($subQueryResolved < $subQueryTotal)";
    }

    /**
     * @param array<int, array<string, mixed>> $results
     *
     * @return array<int, array<string, mixed>>
     */
    private function filterResultsByPermission(array $results): array
    {
        foreach ($results as $key => $record) {
            if (!PermissionUtility::checkAccessForRecord($record['tablename'], $record)) {
                unset($results[$key]);
            }
        }

        return $results;
    }

    /**
     * @param string[] $sql
     */
    private function getSqlByTable(string $table, array &$sql, string $additionalWhere): void
    {
        // Special handling for sys_file_metadata - join with sys_file to get the filename
        if ('sys_file_metadata' === $table) {
            $this->getSqlForFileMetadata($sql, $additionalWhere);

            return;
        }

        // Special handling for folder status table
        if (Configuration::TABLE_FOLDER === $table) {
            $this->getSqlForFolders($sql, $additionalWhere);

            return;
        }

        $titleField = $this->getTitleField($table);

        if ('pages' === $table) {
            $selects = array_merge($this->defaultSelects, [$titleField.' as title, "'.$table.'" as tablename', 'perms_userid', 'perms_groupid', 'perms_user', 'perms_group', 'perms_everybody', 'NULL as storage_uid', 'NULL as folder_identifier']);
        } else {
            $selects = array_merge($this->defaultSelects, [$titleField.' as title, "'.$table.'" as tablename', '0 as perms_userid', '0 as perms_groupid', '0 as perms_user', '0 as perms_group', '0 as perms_everybody', 'NULL as storage_uid', 'NULL as folder_identifier']);
        }

        // Add deleted restriction only for tables that have it
        $deletedWhere = $this->hasDeletedRestriction($table) ? ' AND deleted = 0' : '';

        $sql[] = '(SELECT '.implode(',', $selects).' FROM '.$table.' x WHERE tx_ximatypo3contentplanner_status IS NOT NULL AND tx_ximatypo3contentplanner_status != 0'.$deletedWhere.$additionalWhere.')';
    }

    /**
     * Build SQL for sys_file_metadata with JOIN to sys_file for filename.
     *
     * @param string[] $sql
     */
    private function getSqlForFileMetadata(array &$sql, string $additionalWhere): void
    {
        $table = 'sys_file_metadata';
        $selects = [
            'x.uid',
            'x.pid',
            'x.tstamp',
            'x.tx_ximatypo3contentplanner_status',
            'x.tx_ximatypo3contentplanner_assignee',
            'x.tx_ximatypo3contentplanner_comments',
            'f.name as title',
            "'".$table."' as tablename",
            '0 as perms_userid',
            '0 as perms_groupid',
            '0 as perms_user',
            '0 as perms_group',
            '0 as perms_everybody',
            'NULL as storage_uid',
            'NULL as folder_identifier',
        ];

        $sql[] = '(SELECT '.implode(',', $selects).' FROM '.$table.' x INNER JOIN sys_file f ON x.file = f.uid WHERE x.tx_ximatypo3contentplanner_status IS NOT NULL AND x.tx_ximatypo3contentplanner_status != 0'.$additionalWhere.')';
    }

    /**
     * Build SQL for folder status table with readable folder name.
     *
     * @param string[] $sql
     */
    private function getSqlForFolders(array &$sql, string $additionalWhere): void
    {
        $table = Configuration::TABLE_FOLDER;
        $selects = [
            'uid',
            'pid',
            'tstamp',
            Configuration::FIELD_STATUS,
            Configuration::FIELD_ASSIGNEE,
            Configuration::FIELD_COMMENTS,
            'folder_identifier as title',
            "'".$table."' as tablename",
            '0 as perms_userid',
            '0 as perms_groupid',
            '0 as perms_user',
            '0 as perms_group',
            '0 as perms_everybody',
            'storage_uid',
            'folder_identifier',
        ];

        $sql[] = '(SELECT '.implode(',', $selects).' FROM '.$table.' x WHERE tx_ximatypo3contentplanner_status IS NOT NULL AND tx_ximatypo3contentplanner_status != 0 AND deleted = 0'.$additionalWhere.')';
    }

    private function getTitleField(string $table): string
    {
        return $GLOBALS['TCA'][$table]['ctrl']['label'];
    }

    /**
     * Check if a table has the deleted field restriction.
     * Tables like sys_file_metadata don't have a deleted field.
     */
    private function hasDeletedRestriction(string $table): bool
    {
        return isset($GLOBALS['TCA'][$table]['ctrl']['delete']);
    }

    /**
     * @param array<int, array<string, mixed>> $data
     *
     * @return string[]
     */
    private function collectCacheTags(string $table, array $data, ?int $pid): array
    {
        $tags = [];
        /* @var $item AbstractEntity */
        foreach ($data as $item) {
            if (null !== $item['uid']) {
                $tags[] = $table.'_'.$item['uid'];
            }
        }

        if ((bool) $pid) {
            $tags[] = $table.'__pageId__'.$pid;
        }

        return $tags;
    }
}
