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
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\CommentItem;

use function array_map;
use function count;
use function in_array;

/**
 * CommentRepository.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class CommentRepository
{
    private const TABLE = Configuration::TABLE_COMMENT;

    /** @var array<string, string> */
    protected array $defaultOrderings = [
        'crdate' => QueryInterface::ORDER_DESCENDING,
    ];

    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /**
     * @return array<int, array<string, mixed>>|array<int, CommentItem>
     *
     * @throws Exception
     */
    public function findAllByRecord(int $id, string $table, bool $raw = false, string $sortDirection = 'DESC', bool $showResolved = false): array
    {
        $queryBuilder = $this->buildRootCommentsQueryBuilder($sortDirection);
        $query = $queryBuilder->andWhere(
            $queryBuilder->expr()->eq(self::TABLE.'.foreign_uid', $queryBuilder->createNamedParameter($id, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq(self::TABLE.'.foreign_table', $queryBuilder->createNamedParameter($table, Connection::PARAM_STR)),
        );

        if (!$showResolved) {
            $query->andWhere(
                $queryBuilder->expr()->eq(self::TABLE.'.resolved_date', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            );
        }

        $rootComments = $query
            ->executeQuery()->fetchAllAssociative();

        if ($raw) {
            return $rootComments;
        }

        return $this->hydrateRootComments($rootComments, $showResolved, $sortDirection);
    }

    /**
     * Batched variant of {@see self::findAllByRecord()}: fetches root comments (with replies
     * attached) across several foreign records at once instead of one at a time. Introduced for
     * CP-29 (#328) aggregated child comments, which need the comments of an arbitrary number of
     * child records living on a page in a single query rather than one findAllByRecord() call per
     * child - shares the same join/last-activity/grouping query shape as findAllByRecord() via
     * {@see self::buildRootCommentsQueryBuilder()} instead of duplicating it.
     *
     * @param array<int, array{table: string, uid: int}> $refs
     *
     * @return array<int, CommentItem>
     *
     * @throws Exception
     */
    public function findAllByRecords(array $refs, bool $showResolved = false, string $sortDirection = 'DESC'): array
    {
        if ([] === $refs) {
            return [];
        }

        $queryBuilder = $this->buildRootCommentsQueryBuilder($sortDirection);
        $query = $queryBuilder->andWhere(
            $queryBuilder->expr()->or(...$this->buildForeignRefConditions($queryBuilder, $refs)),
        );

        if (!$showResolved) {
            $query->andWhere(
                $queryBuilder->expr()->eq(self::TABLE.'.resolved_date', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            );
        }

        $rootComments = $query
            ->executeQuery()->fetchAllAssociative();

        return $this->hydrateRootComments($rootComments, $showResolved, $sortDirection);
    }

    /**
     * @throws Exception
     */
    public function countAllByRecord(int $id, string $table, bool $countAll = false, bool $onlyResolved = false): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $query = $queryBuilder
            ->count('uid')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('foreign_uid', $queryBuilder->createNamedParameter($id, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('foreign_table', $queryBuilder->createNamedParameter($table, Connection::PARAM_STR)),
                $queryBuilder->expr()->eq('deleted', 0),
            );

        if (!$countAll && !$onlyResolved) {
            $query->andWhere(
                $queryBuilder->expr()->eq('resolved_date', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            );
        }

        if ($onlyResolved) {
            $query->andWhere(
                $queryBuilder->expr()->neq('resolved_date', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            );
        }

        return $query->executeQuery()->fetchOne();
    }

    public function countTodoAllByRecord(?int $id = null, ?string $table = null, string $todoField = 'todo_resolved', bool $allRecords = false): int
    {
        $allowedFields = ['todo_resolved', 'todo_total'];
        if (!in_array($todoField, $allowedFields, true)) {
            throw new InvalidArgumentException('Invalid todo field: '.$todoField, 1745394753);
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $query = $queryBuilder
            ->selectLiteral("SUM(`$todoField`) AS `check`")
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('resolved_date', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
        ;

        if (!$allRecords) {
            $query->andWhere(
                $queryBuilder->expr()->eq('foreign_uid', $queryBuilder->createNamedParameter($id, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('foreign_table', $queryBuilder->createNamedParameter($table, Connection::PARAM_STR)),
            );
        }

        return (int) $query->executeQuery()->fetchOne();
    }

    /**
     * Sum total and resolved to-dos of a record's open comments in a single query.
     *
     * @return array{total: int, resolved: int}
     *
     * @throws Exception
     */
    public function countTodosByRecord(int $id, string $table): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $row = $queryBuilder
            ->selectLiteral(
                'COALESCE(SUM(`todo_total`), 0) AS `total`',
                'COALESCE(SUM(`todo_resolved`), 0) AS `resolved`',
            )
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('foreign_uid', $queryBuilder->createNamedParameter($id, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('foreign_table', $queryBuilder->createNamedParameter($table, Connection::PARAM_STR)),
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('resolved_date', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchAssociative();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'resolved' => (int) ($row['resolved'] ?? 0),
        ];
    }

    /**
     * Find the UID of the comment with todos for a record - only if exactly one such comment exists.
     * Returns null when multiple comments have todos (caller should fall back to the comments modal).
     */
    public function findSingleTodoCommentUid(int $id, string $table): ?int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);

        $results = $queryBuilder
            ->select('uid')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('foreign_uid', $queryBuilder->createNamedParameter($id, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('foreign_table', $queryBuilder->createNamedParameter($table, Connection::PARAM_STR)),
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('resolved_date', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->gt('todo_total', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->setMaxResults(2)
            ->executeQuery()
            ->fetchFirstColumn();

        return 1 === count($results) ? (int) $results[0] : null;
    }

    /**
     * @return array<string, mixed>|bool
     *
     * @throws Exception
     */
    public function findByUid(int $uid): array|bool
    {
        if (!(bool) $uid) {
            return false;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);

        return $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', 0),
            )
            ->executeQuery()->fetchAssociative();
    }

    public function deleteRepliesByParentUid(int $parentUid): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder
            ->update(self::TABLE)
            ->set('deleted', 1)
            ->where(
                $queryBuilder->expr()->eq('parent_uid', $queryBuilder->createNamedParameter($parentUid, Connection::PARAM_INT)),
            )
            ->executeStatement();
    }

    public function deleteAllCommentsByRecord(int $id, string $table, ?string $like = null): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder
            ->update(self::TABLE)
            ->set('deleted', 1)
            ->where(
                $queryBuilder->expr()->eq('foreign_uid', $queryBuilder->createNamedParameter($id, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('foreign_table', $queryBuilder->createNamedParameter($table, Connection::PARAM_STR)),
            )
        ;

        if ((bool) $like) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->like('content', $queryBuilder->createNamedParameter('%'.$queryBuilder->escapeLikeWildcards($like).'%', Connection::PARAM_STR)),
            );
        }

        $queryBuilder
            ->executeStatement();

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder
            ->update($table)
            ->set(Configuration::FIELD_COMMENTS, 0)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($id, Connection::PARAM_INT)),
            )
            ->executeStatement();
    }

    /**
     * Builds the shared root-comments query: root comments (parent_uid = 0, not deleted) joined
     * with their replies to compute `last_activity` (most recent of the comment's own crdate or
     * any non-deleted reply's), grouped so each root comment yields a single row. Callers add
     * their own foreign-record condition (single record vs. a batch of refs) on top.
     */
    private function buildRootCommentsQueryBuilder(string $sortDirection): QueryBuilder
    {
        // Whitelist the sort direction: QueryBuilder::orderBy() does not quote the direction argument.
        $sortDirection = 'ASC' === strtoupper($sortDirection) ? 'ASC' : 'DESC';

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $replyTable = self::TABLE.'_replies';

        return $queryBuilder
            ->select(self::TABLE.'.*')
            ->from(self::TABLE)
            ->leftJoin(
                self::TABLE,
                self::TABLE,
                $replyTable,
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq($replyTable.'.parent_uid', $queryBuilder->quoteIdentifier(self::TABLE.'.uid')),
                    $queryBuilder->expr()->eq($replyTable.'.deleted', 0),
                ),
            )
            ->addSelectLiteral(
                'CASE WHEN '.$queryBuilder->quoteIdentifier(self::TABLE.'.crdate')
                .' >= COALESCE(MAX('.$queryBuilder->quoteIdentifier($replyTable.'.crdate').'), 0)'
                .' THEN '.$queryBuilder->quoteIdentifier(self::TABLE.'.crdate')
                .' ELSE COALESCE(MAX('.$queryBuilder->quoteIdentifier($replyTable.'.crdate').'), 0)'
                .' END AS last_activity',
            )
            ->where(
                $queryBuilder->expr()->eq(self::TABLE.'.deleted', 0),
                $queryBuilder->expr()->eq(self::TABLE.'.parent_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->groupBy(self::TABLE.'.uid')
            ->orderBy('last_activity', $sortDirection);
    }

    /**
     * Groups refs by table and builds one "foreign_table = X AND foreign_uid IN (...)" condition
     * per table, combined with OR by the caller - refs span multiple foreign tables, so a single
     * IN() on foreign_uid alone would not disambiguate same-uid rows across different tables.
     *
     * @param array<int, array{table: string, uid: int}> $refs
     *
     * @return array<int, \TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression>
     */
    private function buildForeignRefConditions(QueryBuilder $queryBuilder, array $refs): array
    {
        $uidsByTable = [];
        foreach ($refs as $ref) {
            $uidsByTable[$ref['table']][] = $ref['uid'];
        }

        $conditions = [];
        foreach ($uidsByTable as $table => $uids) {
            $conditions[] = $queryBuilder->expr()->and(
                $queryBuilder->expr()->eq(self::TABLE.'.foreign_table', $queryBuilder->createNamedParameter($table, Connection::PARAM_STR)),
                $queryBuilder->expr()->in(self::TABLE.'.foreign_uid', $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)),
            );
        }

        return $conditions;
    }

    /**
     * Shared by {@see self::findAllByRecord()} and {@see self::findAllByRecords()}: turns root
     * comment rows into CommentItem DTOs and attaches their replies (batch-fetched in one query).
     *
     * @param array<int, array<string, mixed>> $rootComments
     *
     * @return array<int, CommentItem>
     *
     * @throws Exception
     */
    private function hydrateRootComments(array $rootComments, bool $showResolved, string $sortDirection): array
    {
        $rootUids = array_map(static fn (array $row): int => (int) $row['uid'], $rootComments);
        $repliesByParent = [] !== $rootUids ? $this->findRepliesByParentUids($rootUids, $showResolved, $sortDirection) : [];

        $items = [];
        foreach ($rootComments as $result) {
            try {
                $item = CommentItem::create($result);
                $item->replies = $repliesByParent[(int) $result['uid']] ?? [];
                $items[] = $item;
            } catch (\Exception) {
            }
        }

        return $items;
    }

    /**
     * @param array<int, int> $parentUids
     *
     * @return array<int, array<int, CommentItem>>
     *
     * @throws Exception
     */
    private function findRepliesByParentUids(array $parentUids, bool $showResolved = false, string $sortDirection = 'DESC'): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);

        $query = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->in('parent_uid', $queryBuilder->createNamedParameter($parentUids, Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->eq('deleted', 0),
            )
            ->orderBy('crdate', $sortDirection);

        if (!$showResolved) {
            $query->andWhere(
                $queryBuilder->expr()->eq('resolved_date', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            );
        }

        $replies = $query->executeQuery()->fetchAllAssociative();

        $grouped = [];
        foreach ($replies as $row) {
            try {
                $grouped[(int) $row['parent_uid']][] = CommentItem::create($row);
            } catch (\Exception) {
            }
        }

        return $grouped;
    }
}
