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
use InvalidArgumentException;
use TYPO3\CMS\Core\Database\{Connection, ConnectionPool};
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\CommentItem;

use function in_array;

/**
 * CommentRepository.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
class CommentRepository
{
    private const TABLE = 'tx_ximatypo3contentplanner_comment';

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
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);

        $query = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('foreign_uid', $queryBuilder->createNamedParameter($id, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('foreign_table', $queryBuilder->createNamedParameter($table, Connection::PARAM_STR)),
                $queryBuilder->expr()->eq('deleted', 0),
            )
            ->orderBy('crdate', $sortDirection);

        if (!$showResolved) {
            $query->andWhere(
                $queryBuilder->expr()->eq('resolved_date', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            );
        }

        $comments = $query
            ->executeQuery()->fetchAllAssociative();

        if ($raw) {
            return $comments;
        }

        $items = [];
        foreach ($comments as $result) {
            try {
                $items[] = CommentItem::create($result);
            } catch (\Exception $e) {
            }
        }

        return $items;
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
            ->set('tx_ximatypo3contentplanner_comments', 0)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($id, Connection::PARAM_INT)),
            )
            ->executeStatement();
    }
}
