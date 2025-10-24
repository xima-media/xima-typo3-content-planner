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
use Xima\XimaTypo3ContentPlanner\Utility\{ExtensionUtility, PermissionUtility};

use function count;
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
        'tx_ximatypo3contentplanner_status',
        'tx_ximatypo3contentplanner_assignee',
        'tx_ximatypo3contentplanner_comments',
    ];

    public function __construct(private readonly FrontendInterface $cache, private readonly ConnectionPool $connectionPool) {}

    /**
     * @return array<int, array<string, mixed>>|bool
     */
    public function findAllByFilter(?string $search = null, ?int $status = null, ?int $assignee = null, ?string $type = null, ?bool $todo = null, int $maxResults = 20): array|bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');

        $additionalWhere = ' AND deleted = 0';
        $additionalParams = [
            'limit' => $maxResults,
        ];

        if ((bool) $search) {
            $additionalWhere .= ' AND (title LIKE :search OR uid = :uid)';
            $additionalParams['search'] = '%'.$search.'%';
            $additionalParams['uid'] = $search;
        }

        if ((bool) $status) {
            $additionalWhere .= ' AND tx_ximatypo3contentplanner_status = :status';
            $additionalParams['status'] = $status;
        }

        if ((bool) $assignee) {
            $additionalWhere .= ' AND tx_ximatypo3contentplanner_assignee = :assignee';
            $additionalParams['assignee'] = $assignee;
        }

        $sqlArray = [];

        foreach (ExtensionUtility::getRecordTables() as $table) {
            if ((bool) $type && $type !== $table) {
                continue;
            }

            $additionalWhereByTable = $additionalWhere;

            if ($todo) {
                // ToDo: Check for performance
                $subQueryTotal = "(SELECT SUM(todo_total) FROM tx_ximatypo3contentplanner_comment WHERE foreign_uid = x.uid AND foreign_table = '$table')";
                $subQueryResolved = "(SELECT SUM(todo_resolved) FROM tx_ximatypo3contentplanner_comment WHERE foreign_uid = x.uid AND foreign_table = '$table')";

                $additionalWhereByTable .= " AND ($subQueryTotal > 0) AND ($subQueryResolved < $subQueryTotal)";
            }

            $this->getSqlByTable($table, $sqlArray, $additionalWhereByTable);
        }

        $sql = implode(' UNION ', $sqlArray).' ORDER BY tstamp DESC LIMIT :limit';

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
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function findByPid(string $table, ?int $pid = null, bool $orderByTstamp = true, bool $ignoreVisibilityRestriction = false): array
    {
        $cacheIdentifier = sprintf('%s--%s--p%s', Configuration::CACHE_IDENTIFIER, $table, $pid);
        if ($this->cache->has($cacheIdentifier)) {
            return $this->cache->get($cacheIdentifier);
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);

        if ($ignoreVisibilityRestriction) {
            $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);
            $queryBuilder->getRestrictions()->removeByType(StartTimeRestriction::class);
            $queryBuilder->getRestrictions()->removeByType(EndTimeRestriction::class);
        }

        $query = $queryBuilder
            ->select('uid', $this->getTitleField($table).' as title', 'tx_ximatypo3contentplanner_status', 'tx_ximatypo3contentplanner_assignee', 'tx_ximatypo3contentplanner_comments')
            ->from($table)
            ->andWhere(
                $queryBuilder->expr()->isNotNull('tx_ximatypo3contentplanner_status'),
                $queryBuilder->expr()->neq('tx_ximatypo3contentplanner_status', 0),
                $queryBuilder->expr()->eq('deleted', 0),
            );

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
            ->select('uid', 'pid', $this->getTitleField($table).' as "title"', 'tx_ximatypo3contentplanner_status', 'tx_ximatypo3contentplanner_assignee', 'tx_ximatypo3contentplanner_comments')
            ->from($table)
            ->andWhere(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', 0),
            );

        return $query->executeQuery()
            ->fetchAssociative();
    }

    public function updateStatusByUid(string $table, int $uid, ?int $status, int|bool|null $assignee = false): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder
            ->update($table)
            ->set('tx_ximatypo3contentplanner_status', $status)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
            );

        if (false !== $assignee) {
            $queryBuilder->set('tx_ximatypo3contentplanner_assignee', $assignee);
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
                ->set('tx_ximatypo3contentplanner_comments', $commentCount)
                ->where(
                    $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
                )
                ->executeStatement();
        }
    }

    /**
     * @param string[] $sql
     */
    private function getSqlByTable(string $table, array &$sql, string $additionalWhere): void
    {
        $titleField = $this->getTitleField($table);

        if ('pages' === $table) {
            $selects = array_merge($this->defaultSelects, [$titleField.' as title, "'.$table.'" as tablename', 'perms_userid', 'perms_groupid', 'perms_user', 'perms_group', 'perms_everybody']);
        } else {
            $selects = array_merge($this->defaultSelects, [$titleField.' as title, "'.$table.'" as tablename', '0 as perms_userid', '0 as perms_groupid', '0 as perms_user', '0 as perms_group', '0 as perms_everybody']);
        }

        $sql[] = '(SELECT '.implode(',', $selects).' FROM '.$table.' x WHERE tx_ximatypo3contentplanner_status IS NOT NULL AND tx_ximatypo3contentplanner_status != 0'.$additionalWhere.')';
    }

    private function getTitleField(string $table): string
    {
        return $GLOBALS['TCA'][$table]['ctrl']['label'];
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
