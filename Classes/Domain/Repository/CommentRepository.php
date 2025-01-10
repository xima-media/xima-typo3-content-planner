<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Domain\Repository;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\CommentItem;

class CommentRepository
{
    private const TABLE = 'tx_ximatypo3contentplanner_comment';

    protected $defaultOrderings = [
        'crdate' => QueryInterface::ORDER_DESCENDING,
    ];

    /**
    * @throws \Doctrine\DBAL\Exception
    */
    public function findAllByRecord(int $id, string $table): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);

        $comments = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('foreign_uid', $queryBuilder->createNamedParameter($id, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('foreign_table', $queryBuilder->createNamedParameter($table, \TYPO3\CMS\Core\Database\Connection::PARAM_STR)),
                $queryBuilder->expr()->eq('deleted', 0)
            )
            ->orderBy('tstamp', 'DESC')
            ->executeQuery()->fetchAllAssociative();

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
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', 0)
            )
            ->executeQuery()->fetchAssociative();
    }

    public function deleteAllCommentsByRecord(int $id, string $table): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);
        $queryBuilder
            ->update(self::TABLE)
            ->set('deleted', 1)
            ->where(
                $queryBuilder->expr()->eq('foreign_uid', $queryBuilder->createNamedParameter($id, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('foreign_table', $queryBuilder->createNamedParameter($table, \TYPO3\CMS\Core\Database\Connection::PARAM_STR))
            )
            ->executeStatement();

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder
            ->update($table)
            ->set('tx_ximatypo3contentplanner_comments', 0)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($id, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
            )
            ->executeStatement();
    }
}
