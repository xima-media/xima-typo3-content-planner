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
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Database\{Connection, ConnectionPool};
use TYPO3\CMS\Core\Database\Query\Restriction\{EndTimeRestriction, HiddenRestriction, StartTimeRestriction};
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\PaginatedResult;
use Xima\XimaTypo3ContentPlanner\Utility\Data\OverfetchPaginator;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function array_slice;
use function count;
use function in_array;
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
    /**
     * Permission filtering happens in PHP after the query (see findAllByFilter()), so the SQL
     * LIMIT alone cannot guarantee $maxResults *visible* rows. This factor over-fetches beyond
     * the requested page size to leave enough headroom for rows the current backend user cannot
     * see. Bounded by FILTER_OVERFETCH_CAP so a large $maxResults cannot blow up the query.
     */
    /**
     * Default number of records per page, shared by every paged read here and by callers
     * that need to mirror it (see ChildCommentAggregationManager).
     */
    public const DEFAULT_PAGE_SIZE = 20;

    private const FILTER_OVERFETCH_FACTOR = 3;

    private const FILTER_OVERFETCH_CAP = 100;

    /**
     * If a whole batch turns out to be invisible, findAllByFilter() pulls the next one rather
     * than reporting a short page. This bounds that loop: with FILTER_OVERFETCH_CAP rows per
     * batch it inspects at most 1000 rows before giving up, so a pathological ratio of
     * invisible rows cannot turn a single request into an unbounded table scan.
     */
    private const FILTER_MAX_BATCHES = 10;

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
     * @return PaginatedResult<array<string, mixed>>
     *
     * @throws Exception
     */
    public function findAllByFilter(?string $search = null, ?int $status = null, ?int $assignee = null, ?string $type = null, ?bool $todo = null, int $maxResults = self::DEFAULT_PAGE_SIZE, bool $openComments = false): PaginatedResult
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');

        $baseWhere = '';
        $batchSize = min($maxResults * self::FILTER_OVERFETCH_FACTOR, self::FILTER_OVERFETCH_CAP);
        $additionalParams = ['limit' => $batchSize];

        [$baseWhere, $additionalParams] = $this->applyFilterConditions($baseWhere, $additionalParams, $search, $status, $assignee);

        $sqlArray = $this->buildUnionQueriesForTables($baseWhere, $type, $todo, $search, $openComments);
        // UNION ALL avoids an unnecessary de-duplication pass: each sub-query carries a distinct
        // tablename literal, so cross-table duplicates cannot occur.
        //
        // tstamp alone is not a total order, so paging by offset could drop or repeat rows on
        // ties. tablename/uid break those ties and are selected by every union branch.
        $sql = implode(' UNION ALL ', $sqlArray).' ORDER BY tstamp DESC, tablename ASC, uid ASC LIMIT :limit OFFSET :offset';

        $connection = $queryBuilder->getConnection();

        return OverfetchPaginator::paginateBatched(
            static function (int $offset) use ($connection, $sql, $additionalParams): array {
                $additionalParams['offset'] = $offset;

                return $connection->executeQuery($sql, $additionalParams)->fetchAllAssociative();
            },
            $maxResults,
            $batchSize,
            self::FILTER_MAX_BATCHES,
            static fn (array $record): bool => PermissionUtility::checkAccessForRecord((string) $record['tablename'], $record),
        );
    }

    /**
     * Count records that carry a status, grouped by status uid, across all tracked tables.
     *
     * @return array<int, int> map of status uid => record count
     *
     * @throws Exception
     */
    public function countRecordsByStatus(): array
    {
        $statusField = Configuration::FIELD_STATUS;
        $counts = [];

        foreach (ExtensionUtility::getRecordTables() as $table) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
            $rows = $queryBuilder
                ->select($statusField)
                ->addSelectLiteral(sprintf('COUNT(%s) AS record_count', $queryBuilder->quoteIdentifier('uid')))
                ->from($table)
                ->where(
                    $queryBuilder->expr()->isNotNull($statusField),
                    $queryBuilder->expr()->neq($statusField, 0),
                )
                ->groupBy($statusField)
                ->executeQuery()
                ->fetchAllAssociative();

            foreach ($rows as $row) {
                $statusUid = (int) $row[$statusField];
                $counts[$statusUid] = ($counts[$statusUid] ?? 0) + (int) $row['record_count'];
            }
        }

        return $counts;
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
            ->select('uid', ExtensionUtility::getTitleField($table).' as title', Configuration::FIELD_STATUS, Configuration::FIELD_ASSIGNEE, Configuration::FIELD_COMMENTS)
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

        // Only registered content planner record tables may be queried through this method
        // (the table name flows into getQueryBuilderForTable()/getTitleField() from request input).
        if (null === $table || !in_array($table, ExtensionUtility::getRecordTables(), true)) {
            return null;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);

        if ($ignoreVisibilityRestriction) {
            $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);
            $queryBuilder->getRestrictions()->removeByType(StartTimeRestriction::class);
            $queryBuilder->getRestrictions()->removeByType(EndTimeRestriction::class);
        }

        $query = $queryBuilder
            ->select('uid', 'pid', ExtensionUtility::getTitleField($table).' as "title"', Configuration::FIELD_STATUS, Configuration::FIELD_ASSIGNEE, Configuration::FIELD_COMMENTS)
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

    /**
     * Find refs (table + uid) of child records living on a page (pid = $pageId) that have at
     * least one comment, across every registered record table - the same pid-generic lookup for
     * `tt_content` on a regular page and, say, `tx_news_domain_model_news` on a sysfolder page.
     * `pages` (the page itself, not a child of itself) and the folder status table (identified by
     * folder_identifier/storage_uid rather than uid+pid) are excluded (CP-29, #328).
     *
     * Deliberately queried per table with a portable QueryBuilder (unlike findAllByFilter()'s raw
     * UNION SQL) so this stays testable against SQLite, not just MySQL.
     *
     * @return PaginatedResult<array<string, mixed>>
     *
     * @throws Exception
     */
    public function findChildRecordRefsWithComments(int $pageId, int $maxResults = self::DEFAULT_PAGE_SIZE): PaginatedResult
    {
        $fetchLimit = min($maxResults * self::FILTER_OVERFETCH_FACTOR, self::FILTER_OVERFETCH_CAP);

        $rows = [];
        foreach ($this->getChildCommentTables() as $table) {
            $rows = array_merge($rows, $this->findChildRowsForTable($table, $pageId, $fetchLimit));
        }

        usort($rows, static fn (array $a, array $b): int => (int) $b['tstamp'] <=> (int) $a['tstamp']);
        $rows = array_slice($rows, 0, $fetchLimit);

        return OverfetchPaginator::paginate(
            $rows,
            $maxResults,
            static fn (array $record): bool => PermissionUtility::checkAccessForRecord((string) $record['tablename'], $record),
        );
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

        $record = $this->findByUid($table, $uid, true);
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
     * @return string[]
     */
    private function getChildCommentTables(): array
    {
        return array_values(array_diff(
            ExtensionUtility::getRecordTables(),
            ['pages', Configuration::TABLE_FOLDER],
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    private function findChildRowsForTable(string $table, int $pageId, int $limit): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $titleField = ExtensionUtility::getTitleField($table);

        $query = $queryBuilder
            ->select('uid', 'pid', 'tstamp', Configuration::FIELD_STATUS, Configuration::FIELD_ASSIGNEE, Configuration::FIELD_COMMENTS)
            ->addSelectLiteral($queryBuilder->quoteIdentifier($titleField).' AS title')
            ->addSelectLiteral($queryBuilder->quote($table).' AS tablename')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)),
                $queryBuilder->expr()->gt(Configuration::FIELD_COMMENTS, $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->orderBy('tstamp', 'DESC')
            ->setMaxResults($limit);

        if ($this->hasDeletedRestriction($table)) {
            $query->andWhere($queryBuilder->expr()->eq('deleted', 0));
        }

        return $query->executeQuery()->fetchAllAssociative();
    }

    /**
     * @param array<string, mixed> $additionalParams
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function applyFilterConditions(string $baseWhere, array $additionalParams, ?string $search, ?int $status, ?int $assignee): array
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

        return [$baseWhere, $additionalParams];
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

        return ExtensionUtility::getTitleField($table);
    }

    /**
     * @return string[]
     */
    private function buildUnionQueriesForTables(string $baseWhere, ?string $type, ?bool $todo, ?string $search = null, bool $openComments = false): array
    {
        $sqlArray = [];

        foreach (ExtensionUtility::getRecordTables() as $table) {
            if ((bool) $type && $type !== $table) {
                continue;
            }

            $whereClause = $this->buildWhereClauseForTable($baseWhere, $table, $todo, $openComments);
            $whereClause .= $this->buildSearchCondition($table, $search);
            $sqlArray[] = $this->getSqlByTable($table, $whereClause);
        }

        return $sqlArray;
    }

    private function buildWhereClauseForTable(string $baseWhere, string $table, ?bool $todo, bool $openComments = false): string
    {
        $where = $baseWhere;
        $commentBase = "FROM tx_ximatypo3contentplanner_comment WHERE foreign_uid = x.uid AND foreign_table = '$table' AND deleted = 0";

        if ($todo) {
            $subQueryTotal = "(SELECT SUM(todo_total) $commentBase)";
            $subQueryResolved = "(SELECT SUM(todo_resolved) $commentBase)";
            $where .= " AND ($subQueryTotal > 0) AND ($subQueryResolved < $subQueryTotal)";
        }

        if ($openComments) {
            $subQueryOpenComments = "(SELECT COUNT(*) $commentBase AND resolved_date = 0)";
            $where .= " AND ($subQueryOpenComments > 0)";
        }

        return $where;
    }

    private function getSqlByTable(string $table, string $additionalWhere): string
    {
        // Special handling for sys_file_metadata - join with sys_file to get the filename
        if ('sys_file_metadata' === $table) {
            return $this->getSqlForFileMetadata($additionalWhere);
        }

        // Special handling for folder status table
        if (Configuration::TABLE_FOLDER === $table) {
            return $this->getSqlForFolders($additionalWhere);
        }

        $titleField = ExtensionUtility::getTitleField($table);

        if ('pages' === $table) {
            $selects = array_merge($this->defaultSelects, [$titleField.' as title, "'.$table.'" as tablename', 'perms_userid', 'perms_groupid', 'perms_user', 'perms_group', 'perms_everybody', 'NULL as storage_uid', 'NULL as folder_identifier']);
        } else {
            $selects = array_merge($this->defaultSelects, [$titleField.' as title, "'.$table.'" as tablename', '0 as perms_userid', '0 as perms_groupid', '0 as perms_user', '0 as perms_group', '0 as perms_everybody', 'NULL as storage_uid', 'NULL as folder_identifier']);
        }

        // Add deleted restriction only for tables that have it
        $deletedWhere = $this->hasDeletedRestriction($table) ? ' AND deleted = 0' : '';

        return '(SELECT '.implode(',', $selects).' FROM '.$table.' x WHERE tx_ximatypo3contentplanner_status IS NOT NULL AND tx_ximatypo3contentplanner_status != 0'.$deletedWhere.$additionalWhere.')';
    }

    /**
     * Build SQL for sys_file_metadata with JOIN to sys_file for filename.
     */
    private function getSqlForFileMetadata(string $additionalWhere): string
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

        return '(SELECT '.implode(',', $selects).' FROM '.$table.' x INNER JOIN sys_file f ON x.file = f.uid WHERE x.tx_ximatypo3contentplanner_status IS NOT NULL AND x.tx_ximatypo3contentplanner_status != 0'.$additionalWhere.')';
    }

    /**
     * Build SQL for folder status table with readable folder name.
     */
    private function getSqlForFolders(string $additionalWhere): string
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

        return '(SELECT '.implode(',', $selects).' FROM '.$table.' x WHERE tx_ximatypo3contentplanner_status IS NOT NULL AND tx_ximatypo3contentplanner_status != 0 AND deleted = 0'.$additionalWhere.')';
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
