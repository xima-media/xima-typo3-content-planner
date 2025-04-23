<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\CommentItem;

class CommentRepository
{
    private const TABLE = 'tx_ximatypo3contentplanner_comment';

    protected array $defaultOrderings = [
        'crdate' => QueryInterface::ORDER_DESCENDING,
    ];

    /**
    * @throws \Doctrine\DBAL\Exception
    */
    public function findAllByRecord(int $id, string $table, bool $raw = false): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);

        $comments = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('foreign_uid', $queryBuilder->createNamedParameter($id, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('foreign_table', $queryBuilder->createNamedParameter($table, Connection::PARAM_STR)),
                $queryBuilder->expr()->eq('deleted', 0)
            )
            ->orderBy('crdate', 'DESC')
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

    public function countAllByRecord(int $id, string $table): int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);
        return $queryBuilder
            ->count('uid')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('foreign_uid', $queryBuilder->createNamedParameter($id, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('foreign_table', $queryBuilder->createNamedParameter($table, Connection::PARAM_STR)),
                $queryBuilder->expr()->eq('deleted', 0)
            )
            ->executeQuery()->fetchOne();
    }

    public function countTodoAllByRecord(?int $id = null, ?string $table = null, string $todoField = 'todo_resolved', bool $allRecords = false): int
    {
        $allowedFields = ['todo_resolved', 'todo_total'];
        if (!in_array($todoField, $allowedFields, true)) {
            throw new \InvalidArgumentException('Invalid todo field: ' . $todoField, 1745394753);
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);
        $query = $queryBuilder
            ->selectLiteral("SUM(`$todoField`) AS `check`")
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('deleted', 0))
        ;

        if (!$allRecords) {
            $query->andWhere(
                $queryBuilder->expr()->eq('foreign_uid', $queryBuilder->createNamedParameter($id, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('foreign_table', $queryBuilder->createNamedParameter($table, Connection::PARAM_STR))
            );
        }

        return (int)$query->executeQuery()->fetchOne();
    }

    /**
    * @throws \Doctrine\DBAL\Exception
    */
    public function findByUid(int $uid): array|bool
    {
        if (!$uid) {
            return false;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);

        return $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', 0)
            )
            ->executeQuery()->fetchAssociative();
    }

    public function deleteAllCommentsByRecord(int $id, string $table, ?string $like = null): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);
        $queryBuilder
            ->update(self::TABLE)
            ->set('deleted', 1)
            ->where(
                $queryBuilder->expr()->eq('foreign_uid', $queryBuilder->createNamedParameter($id, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('foreign_table', $queryBuilder->createNamedParameter($table, Connection::PARAM_STR))
            )
        ;

        if ($like) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->like('content', $queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards($like) . '%', Connection::PARAM_STR))
            );
        }

        $queryBuilder
            ->executeStatement();

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder
            ->update($table)
            ->set('tx_ximatypo3contentplanner_comments', 0)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($id, Connection::PARAM_INT)),
            )
            ->executeStatement();
    }
}
